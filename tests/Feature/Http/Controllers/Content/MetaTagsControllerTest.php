<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
});

function metaScreen(string $query = '', Role $role = Role::SeoSpecialist): string
{
    return (string) test()->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/meta-tags'.$query)
        ->assertOk()
        ->getContent();
}

/**
 * The site of the design, smaller: Home in order, Trainingstijden with a title that is too long and no description,
 * Onze teams with a description near the limit, Contact with a description that is too long, and a draft.
 *
 * @return array{home: Page, training: Page, teams: Page, contact: Page, draft: Page}
 */
function metaScreenSite(): array
{
    useStripTypes();

    return [
        'home' => Page::factory()->published()->at('/')->atPosition(0)->withMeta(null, 'Welkom bij de club.')->create(['title' => 'Home']),
        'training' => Page::factory()->published()->at('/trainingstijden')->atPosition(1)->withMeta(str_repeat('t', 71))
            ->withStrips([storedStrip('hero', ['intro' => 'We trainen op dinsdag en donderdag in Mountain Network. Kom gerust een keer meespelen.'])])
            ->create(['title' => 'Trainingstijden']),
        'teams' => Page::factory()->published()->at('/teams')->atPosition(2)->withMeta('Onze teams', str_repeat('o', 150))->create(['title' => 'Onze teams']),
        'contact' => Page::factory()->published()->at('/contact')->atPosition(3)->withMeta('Contact', str_repeat('c', 160))->create(['title' => 'Contact']),
        'draft' => Page::factory()->at('/nieuw')->atPosition(4)->create(['title' => 'Nieuw']),
    ];
}

it('opens the Meta-overzicht at its own address', function (): void {
    expect(route('made-cms-package.screens.meta-tags', [], false))->toBe('/made-cms/meta-tags');
});

it('opens for whoever opens the meta overview, and for no one else', function (Role $role, bool $opens): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/meta-tags')
        ->assertStatus($opens ? 200 : 403);
})->with([
    'the admin' => [Role::Admin, true],
    'the seo specialist' => [Role::SeoSpecialist, true],
    'the content manager' => [Role::ContentManager, false],
    'the webmaster' => [Role::Webmaster, false],
    'the owner' => [Role::Owner, false],
]);

it('sends a guest to the login page', function (): void {
    $this->get('made-cms/meta-tags')->assertRedirect(route('made-cms-package.login'));
});

it('is never stored by a cache, framed or indexed', function (): void {
    $response = $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms')
        ->get('made-cms/meta-tags')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('says what the screen is for and how many pages need work, with the way to work through them', function (): void {
    ['training' => $training] = metaScreenSite();

    expect(metaScreen())
        ->toContain('<span class="eyebrow">Vindbaarheid</span>')
        ->toContain('<h1 id="made-cms-titel">Meta-overzicht</h1>')
        ->toContain('<p class="intro">Titel en omschrijving zijn wat Google van een pagina laat zien. Elke pagina hoort er één van elk te hebben, kort genoeg om niet afgekapt te worden. 2 pagina’s halen dat nu niet; je loopt ze hier af in de volgorde van het menu.</p>')
        ->toContain('<a class="knop" href="/made-cms/meta-tags?filter=missing&amp;page='.$training->id.'#made-cms-afwerken">Werk de 2 punten af</a>')
        ->toContain('<a class="navitem actief" href="/made-cms/meta-tags" aria-current="page">');
});

it('filters the pages google shows by how their texts measure up, and offers all pages', function (): void {
    metaScreenSite();

    expect(metaScreen())->toContain(
        '<button type="submit" class="filter" name="filter" value="missing" aria-pressed="true" data-filter="missing"><span class="filter-bol alarm" aria-hidden="true"></span>1 zonder omschrijving</button>',
        '<button type="submit" class="filter" name="filter" value="long" aria-pressed="false" data-filter="long"><span class="filter-bol let" aria-hidden="true"></span>1 te lang</button>',
        '<button type="submit" class="filter" name="filter" value="ok" aria-pressed="false" data-filter="ok"><span class="filter-bol goed" aria-hidden="true"></span>2 in orde</button>',
        '<button type="submit" class="filter" name="filter" value="all" aria-pressed="false" data-filter="all">Alle 5 pagina’s</button>',
    );
});

it('keeps the open page and the progress of the round when the filter changes', function (): void {
    ['contact' => $contact] = metaScreenSite();

    expect(metaScreen('?page='.$contact->id.'&done=1'))
        ->toContain('<input type="hidden" name="page" value="'.$contact->id.'">')
        ->toContain('<input type="hidden" name="done" value="1">');
});

it('lists the pages in the order of the menu with a bar for the title and the description', function (): void {
    ['home' => $home] = metaScreenSite();

    $html = metaScreen('?filter=all');

    expect($html)->toContain(
        '<a class="metarij" href="/made-cms/meta-tags?filter=all&amp;page='.$home->id.'#made-cms-afwerken">',
        '<span class="metabalk past"><svg viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="metabalk-spoor" width="100" height="6"/><rect class="metabalk-vulling" width="22" height="6"/></svg><span class="sr-only">Titel 13 van 60 tekens</span></span>',
        '<span class="metabalk te-lang"><svg viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="metabalk-spoor" width="100" height="6"/><rect class="metabalk-vulling" width="100" height="6"/></svg><span class="sr-only">Titel 71 tekens, te lang</span></span>',
        '<span class="sr-only">Omschrijving ontbreekt</span>',
        '<span class="metabalk krap"><svg viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="metabalk-spoor" width="100" height="6"/><rect class="metabalk-vulling" width="97" height="6"/></svg><span class="sr-only">Omschrijving 150 van 155 tekens, bijna te lang</span></span>',
        '<span class="sr-only">Omschrijving 160 tekens, te lang</span>',
        '<span class="metarij-reden">Concept: Google toont hem nog niet</span>',
        '<p class="metalijst-uitleg">De balkjes zijn de lengte ten opzichte van wat Google toont: 60 tekens voor een titel, 155 voor een omschrijving. Rood is leeg of te lang, amber loopt tegen de grens aan.</p>',
    )->and(preg_match_all('/<span class="metarij-naam">([^<]+)<\/span>/', $html, $names))->toBe(5)
        ->and($names[1])->toBe(['Home', 'Trainingstijden', 'Onze teams', 'Contact', 'Nieuw']);
});

it('opens the first point beside the list, with its place in the round and what Google shows', function (): void {
    ['training' => $training, 'contact' => $contact] = metaScreenSite();

    expect(metaScreen())->toContain(
        '<a class="metarij open" href="/made-cms/meta-tags?filter=missing&amp;page='.$training->id.'#made-cms-afwerken" aria-current="true">',
        '<section class="kaart metapaneel" id="made-cms-afwerken" tabindex="-1" aria-labelledby="made-cms-meta-pagina">',
        '<span class="eyebrow">Pagina 1 van 2</span>',
        '<h2 id="made-cms-meta-pagina">Trainingstijden</h2>',
        '<span class="stappen" aria-hidden="true"><span class="stap actief"></span><span class="stap"></span></span>',
        '<span class="metavoortgang-tekst">0 van 2 gedaan</span>',
        '<form class="formulier" method="post" action="/made-cms/meta-tags/'.$training->id.'" data-meta',
        '<input type="hidden" name="filter" value="missing">',
        '<figcaption class="google-bijschrift">Zo toont Google het, ongeveer</figcaption>',
        '<span class="google-adres">localhost › trainingstijden</span>',
        '<span class="google-tekst leeg" data-meta-preview="description">Geen omschrijving: Google kiest zelf een stuk tekst van de pagina.</span>',
        'placeholder="Trainingstijden · TTV Merwestad"',
        'aria-describedby="made-cms-meta_title-lengte"',
        '<span class="sr-only" id="made-cms-meta_title-lengte" data-meta-said="title">Titel 71 tekens, te lang</span>',
        '<span class="metameter-teller veld-teller te-lang" aria-hidden="true" data-meta-count="title">71 / 60</span>',
        '<span class="metameter-teller veld-teller ontbreekt" aria-hidden="true" data-meta-count="description">0 / 155</span>',
        '<button type="submit" class="knop">Opslaan en volgende</button>',
        '<a class="tekstlink" href="/made-cms/meta-tags?filter=missing&amp;page='.$contact->id.'#made-cms-afwerken">Overslaan</a>',
        '<p class="metapaneel-noot">Opslaan zet het direct live; de sitemap gaat vanzelf mee.</p>',
    );
});

it('proposes a description from the text of the page, with its length', function (): void {
    metaScreenSite();

    expect(metaScreen())->toContain(
        '<span class="metavoorstel-kop">Voorstel uit de paginatekst</span>',
        '<p class="metavoorstel-tekst" data-meta-suggestion>We trainen op dinsdag en donderdag in Mountain Network. Kom gerust een keer meespelen.</p>',
        '<button type="button" class="knop stil" hidden data-meta-suggest>Gebruik dit voorstel</button>',
        '<span class="metavoorstel-lengte">86 tekens, past.</span>',
    );
});

it('passes the last point by to a new round with the points passed by', function (): void {
    ['contact' => $contact] = metaScreenSite();

    expect(metaScreen('?page='.$contact->id.'&done=1'))
        ->toContain('<span class="eyebrow">Pagina 3 van 3</span>')
        ->toContain('<span class="stappen" aria-hidden="true"><span class="stap gedaan"></span><span class="stap"></span><span class="stap actief"></span></span>')
        ->toContain('<span class="metavoortgang-tekst">1 van 3 gedaan</span>')
        ->toContain('<a class="tekstlink" href="/made-cms/meta-tags?filter=missing#made-cms-afwerken">Overslaan</a>');
});

it('opens the only point left without a way to pass it by', function (): void {
    Page::factory()->published()->at('/')->withMeta(null, 'Welkom bij de club.')->create(['title' => 'Home']);
    Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams']);

    expect(metaScreen('?done=1'))
        ->toContain('<span class="eyebrow">Pagina 2 van 2</span>')
        ->toContain('<span class="metavoortgang-tekst">1 van 2 gedaan</span>')
        ->not->toContain('>Overslaan</a>')
        ->not->toContain('class="metavoorstel"');
});

it('opens a page that is no point on its own, with the plain save', function (): void {
    ['teams' => $teams, 'draft' => $draft] = metaScreenSite();

    expect(metaScreen('?page='.$teams->id))
        ->toContain('<span class="eyebrow">In orde</span>')
        ->toContain('<button type="submit" class="knop">Opslaan</button>')
        ->not->toContain('class="metavoortgang"')
        ->not->toContain('>Overslaan</a>')
        ->and(metaScreen('?filter=all&page='.$draft->id))
        ->toContain('<span class="eyebrow">Concept: Google toont hem nog niet</span>');
});

it('shows one bar for the progress of a long round', function (): void {
    for ($i = 0; $i < 13; $i++) {
        Page::factory()->published()->at('/pagina-'.$i)->atPosition($i)->create();
    }

    expect(metaScreen('?done=4'))
        ->toContain('<span class="eyebrow">Pagina 5 van 17</span>')
        ->toContain('<svg class="metavoortgang-balk" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="metabalk-spoor" width="100" height="6"/><rect class="metabalk-vulling" width="24" height="6"/></svg>')
        ->not->toContain('class="stappen"');
});

it('says when every page Google shows is in order', function (): void {
    Page::factory()->published()->at('/')->withMeta(null, 'Welkom bij de club.')->create(['title' => 'Home']);
    Page::factory()->published()->at('/teams')->withMeta('Onze teams', 'Alle teams van de club.')->create(['title' => 'Onze teams']);

    expect(metaScreen())
        ->toContain('Alle 2 pagina’s die Google toont hebben er een van elk, kort genoeg om niet afgekapt te worden. Er is niets te doen; verandert er iets, dan zie je het hier weer.</p>')
        ->toContain('data-filter="ok"><span class="filter-bol goed" aria-hidden="true"></span>2 in orde</button>')
        ->toContain('<span class="metastand-klaar">Niets zonder omschrijving, niets te lang.</span>')
        ->toContain('<h2 id="made-cms-meta-klaar">Elke pagina heeft een titel en een omschrijving die passen.</h2>')
        ->toContain('<p class="kaart-tekst">Titels blijven onder de 60 tekens, omschrijvingen onder de 155.')
        ->toContain('<p class="kaart-tekst">Wil je toch iets aanpassen? Klik een pagina in de lijst.</p>')
        ->not->toContain('data-filter="missing"')
        ->not->toContain('class="knop" href="/made-cms/meta-tags?')
        ->not->toContain('Wat je nu kunt doen')
        ->not->toContain('class="kaart metapaneel"');
});

it('says when Google shows no page of the site yet', function (): void {
    Page::factory()->at('/nieuw')->create(['title' => 'Nieuw']);

    expect(metaScreen())
        ->toContain('Google toont nog geen pagina van de site. Een pagina telt hier mee zodra hij gepubliceerd is en Google hem mag tonen.</p>')
        ->toContain('data-filter="all">1 pagina</button>')
        ->toContain('<span class="metarij-reden">Concept: Google toont hem nog niet</span>')
        ->not->toContain('class="kaart metaklaar"');
});

it('never refuses its address: what it does not know is left out', function (string $query): void {
    metaScreenSite();

    expect(metaScreen($query))->toContain('<span class="eyebrow">Pagina 1 van 2</span>');
})->with([
    'lists' => ['?filter[]=all&page[]=1&done[]=2'],
    'unknown filter' => ['?filter=zwembad'],
    'a page that is not there' => ['?page=999999999'],
    'a page id far too large' => ['?page=99999999999999999999999'],
    'negative numbers' => ['?page=-3&done=-2'],
    'a round too large' => ['?done=99999999'],
]);

it('asks the database the same, however many pages the site has', function (): void {
    $queries = 0;
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries++;
    });
    $count = function () use (&$queries): int {
        $queries = 0;
        metaScreen();

        return $queries;
    };

    metaScreenSite();
    $few = $count();
    Page::factory()->count(12)->sequence(fn ($sequence) => ['position' => 10 + $sequence->index])
        ->published()->withStrips([storedStrip()])
        ->create()
        ->each(fn (Page $page) => $page->route()->create(['path' => '/extra-'.$page->id]));

    expect($count())->toBe($few);
});
