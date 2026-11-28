<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\BuildSitemap;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'app.name' => 'TTV Merwestad', 'made-cms-package.sitemap.robots' => ['*' => ['/zoeken']]]);
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));
    usePublicFolder();
});

function sitemapScreen(string $query = '', Role $role = Role::SeoSpecialist): string
{
    return (string) test()->actingAs(Editor::factory()->withRoles($role)->create(['name' => 'Muriël']), 'made-cms')
        ->get('made-cms/sitemap'.$query)
        ->assertOk()
        ->getContent();
}

/**
 * The site of the design (17.3, 17.12): pages in the sitemap, one under another, a draft, one robots.txt keeps away,
 * and three kept out on purpose.
 *
 * @return array<string, Page>
 */
function sitemapScreenSite(): array
{
    $home = Page::factory()->published()->at('/')->atPosition(0)->create(['title' => 'Home', 'published_at' => CarbonImmutable::parse('2026-11-28 09:20:00')]);
    $teams = Page::factory()->published()->at('/teams')->atPosition(1)->create(['title' => 'Onze teams', 'published_at' => CarbonImmutable::parse('2026-11-14 12:00:00')]);
    Page::factory()->published()->under($teams)->at('/teams/merwestad-1')->create(['title' => 'Merwestad 1', 'published_at' => CarbonImmutable::parse('2026-11-14 12:00:00')]);
    Page::factory()->at('/jeugd')->atPosition(2)->create(['title' => 'Jeugd']);
    Page::factory()->published()->at('/zoeken')->atPosition(3)->create(['title' => 'Zoeken', 'published_at' => CarbonImmutable::parse('2026-03-02 12:00:00')]);
    Page::factory()->published()->noindex()->at('/proefles')->atPosition(4)->create(['title' => 'Proefles aanvragen', 'published_at' => CarbonImmutable::parse('2026-09-10 12:00:00')]);
    Page::factory()->published()->hidden()->at('/bedankt')->atPosition(5)->create(['title' => 'Bedankt', 'published_at' => CarbonImmutable::parse('2026-09-10 12:00:00')]);
    $privacy = Page::factory()->published()->outOfSitemap()->at('/privacy')->atPosition(6)->create(['title' => 'Privacy', 'published_at' => CarbonImmutable::parse('2026-03-02 12:00:00')]);

    return ['home' => $home, 'teams' => $teams, 'privacy' => $privacy];
}

function builtSitemap(SitemapCause $cause = SitemapCause::Published, ?int $pageId = null, ?int $editorId = null): Sitemap
{
    return BuildSitemap::run('', new SitemapReason($cause, $pageId, $editorId));
}

it('is the screen of the sitemap at its own address', function (): void {
    expect(route('made-cms-package.screens.sitemap', [], false))->toBe('/made-cms/sitemap');
});

it('opens for the admin and the SEO specialist only', function (Role $role, int $status): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/sitemap')
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'seo specialist' => [Role::SeoSpecialist, 200],
    'content manager' => [Role::ContentManager, 403],
    'webmaster' => [Role::Webmaster, 403],
    'owner' => [Role::Owner, 403],
]);

it('sends a guest to the login', function (): void {
    $this->get('made-cms/sitemap')->assertRedirect(route('made-cms-package.login'));
});

it('is never stored, framed or indexed', function (): void {
    $response = $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms')->get('made-cms/sitemap');

    $response->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('says what the sitemap is under its heading', function (): void {
    servePages();

    expect(sitemapScreen())
        ->toContain('<span class="eyebrow">Vindbaarheid</span>')
        ->toContain('<h1 id="made-cms-titel">Sitemap</h1>')
        ->toContain('<p class="intro">De sitemap is de lijst die je aan Google geeft: deze pagina’s bestaan, en dit is wanneer ze voor het laatst veranderden.')
        ->toContain('<a class="navitem actief" href="/made-cms/sitemap" aria-current="page">');
});

it('counts the addresses of the sitemap and says when and why it was made', function (): void {
    $site = sitemapScreenSite();
    servePages();
    $this->travelTo(CarbonImmutable::parse('2026-11-28 09:20:00'));
    builtSitemap(SitemapCause::Published, $site['home']->id);
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));

    expect(sitemapScreen())
        ->toContain('<span class="standkaart-getal">3</span>')
        ->toContain('<span class="standkaart-tekst">adressen in de sitemap</span>')
        ->toContain('<span class="standkaart-sub">Gemaakt vandaag, 09:20, bij het publiceren van Home</span>');
});

it('says what led to the sitemap', function (SitemapCause $cause, bool $withPage, bool $withEditor, string $said): void {
    $page = Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams']);
    $editor = Editor::factory()->create(['name' => 'Menno']);
    servePages();
    builtSitemap($cause, $withPage ? $page->id : null, $withEditor ? $editor->id : null);

    expect(sitemapScreen())->toContain('Gemaakt vandaag, 10:00, '.$said.'</span>');
})->with([
    'published' => [SitemapCause::Published, true, false, 'bij het publiceren van Onze teams'],
    'saved' => [SitemapCause::Updated, true, false, 'bij het opslaan van Onze teams'],
    'moved' => [SitemapCause::Moved, true, false, 'bij het verplaatsen van Onze teams'],
    'deleted' => [SitemapCause::Deleted, true, false, 'bij het verwijderen van Onze teams'],
    'restored' => [SitemapCause::Restored, true, false, 'bij het terugzetten van Onze teams'],
    'hidden or shown' => [SitemapCause::Visibility, true, false, 'bij het verbergen of tonen van Onze teams'],
    'meta' => [SitemapCause::Meta, true, false, 'bij een wijziging van Onze teams voor Google'],
    'a page that is gone' => [SitemapCause::Published, false, false, 'bij het publiceren van een pagina'],
    'the command' => [SitemapCause::Command, false, false, 'met made-cms:sitemap'],
    'the first request' => [SitemapCause::Requested, false, false, 'toen hij voor het eerst werd opgevraagd'],
    'by hand' => [SitemapCause::Manual, false, true, 'met de hand door Menno'],
    'by hand, by someone gone' => [SitemapCause::Manual, false, false, 'met de hand'],
]);

it('names the pages kept out of the sitemap on purpose', function (): void {
    sitemapScreenSite();
    servePages();

    expect(sitemapScreen())
        ->toContain('<span class="standkaart-getal">3</span>')
        ->toContain('<span class="standkaart-tekst">pagina’s bewust buiten de sitemap</span>')
        ->toContain('<span class="standkaart-sub">Proefles aanvragen, Bedankt en Privacy</span>');
});

it('names the first three pages kept out and counts the others', function (): void {
    foreach (['A', 'B', 'C', 'D', 'E'] as $index => $title) {
        Page::factory()->published()->outOfSitemap()->at('/'.strtolower($title))->atPosition($index)->create(['title' => $title]);
    }
    servePages();

    expect(sitemapScreen())->toContain('<span class="standkaart-sub">A, B, C en 2 andere</span>');
});

it('says every published page may be in it when none is kept out', function (): void {
    Page::factory()->published()->at('/')->create(['title' => 'Home']);
    servePages();

    expect(sitemapScreen())
        ->toContain('<span class="standkaart-getal">0</span>')
        ->toContain('<span class="standkaart-sub">Elke gepubliceerde pagina mag erin.</span>');
});

it('says when Google last fetched the sitemap, counted by the CMS itself', function (): void {
    sitemapScreenSite();
    servePages();
    AddressHit::factory()->at('/sitemap.xml')->outcome(HitOutcome::Served)->from(VisitSource::Googlebot)->daysAgo(3)->create();

    expect(sitemapScreen())
        ->toContain('<span class="standkaart-kop"><span class="bol goed" aria-hidden="true"></span>Google haalde de sitemap 3 dagen geleden op</span>')
        ->toContain('Geteld door het CMS zelf, niet door Google. Sinds die dag is 1 pagina gewijzigd. Aanmelden hoeft niet: Google vindt hem via robots.txt.')
        ->toContain('<a class="standkaart-bestand" href="https://ttvmerwestad.nl/sitemap.xml">ttvmerwestad.nl/sitemap.xml</a>')
        ->toContain('<a class="standkaart-bestand" href="https://ttvmerwestad.nl/robots.txt">ttvmerwestad.nl/robots.txt</a>');
});

it('says Google fetched the sitemap today or yesterday', function (int $daysAgo, string $when): void {
    servePages();
    AddressHit::factory()->at('/sitemap.xml')->outcome(HitOutcome::Served)->from(VisitSource::Googlebot)->daysAgo($daysAgo)->create();

    expect(sitemapScreen())->toContain('Google haalde de sitemap '.$when.' op</span>');
})->with([
    'today' => [0, 'vandaag'],
    'yesterday' => [1, 'gisteren'],
]);

it('says in amber that Google has not fetched the sitemap yet, since when it counts and where Googlebot did come', function (): void {
    servePages();
    AddressHit::factory()->at('/teams')->outcome(HitOutcome::Page)->from(VisitSource::Googlebot)->daysAgo(20)->hits(41)->create();

    expect(sitemapScreen())
        ->toContain('<div class="standkaart teller let">')
        ->toContain('<span class="bol let" aria-hidden="true"></span>Google haalde de sitemap nog niet op</span>')
        ->toContain('Geteld door het CMS zelf, sinds 8 november. Googlebot kwam wel 41 keer op pagina’s, maar nooit bij /sitemap.xml.');
});

it('says nothing is counted yet before the CMS counted anything', function (): void {
    servePages();

    expect(sitemapScreen())->toContain('Geteld door het CMS zelf; er is nog niets geteld.');
});

it('shows the file itself: when it was made, its addresses, how it starts and how to open it', function (): void {
    sitemapScreenSite();
    servePages();
    builtSitemap(SitemapCause::Command);

    $screen = sitemapScreen();

    expect($screen)
        ->toContain('<h2 id="made-cms-sitemap-bestand-kop">Het bestand zelf</h2>')
        ->toContain('Laatst gemaakt vandaag, 10:00, met made-cms:sitemap · 3 adressen · 1 fout')
        ->toContain('<pre class="codeblok">&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot;?&gt;'."\n".'&lt;urlset xmlns=&quot;http://www.sitemaps.org/schemas/sitemap/0.9&quot;&gt;'."\n".'  &lt;url&gt;'."\n".'    &lt;loc&gt;https://ttvmerwestad.nl/&lt;/loc&gt;')
        ->toContain('<a class="tekstlink" href="https://ttvmerwestad.nl/sitemap.xml">Open sitemap.xml</a>')
        ->toContain('3 adressen. Proefles aanvragen, Bedankt en Privacy blijven erbuiten. Wijzigingsdatum = laatste publicatie, geen prioriteit of frequentie (zoekmachines negeren die).')
        ->toContain('<form class="sitemapbestand-knoppen" method="post" action="/made-cms/sitemap/build" data-busy');
});

it('shows only the first lines of a long sitemap', function (): void {
    foreach (range(1, 8) as $index) {
        Page::factory()->published()->at('/pagina-'.$index)->atPosition($index)->create();
    }
    servePages();
    builtSitemap(SitemapCause::Command);

    preg_match('/<pre class="codeblok">(.*?)<\/pre>/s', sitemapScreen(), $match);

    expect(explode("\n", $match[1]))->toHaveCount(15)
        ->and(explode("\n", $match[1])[14])->toBe('…');
});

it('names what belongs in the sitemap but is left out of the file, with the reason', function (): void {
    sitemapScreenSite();
    servePages();
    builtSitemap(SitemapCause::Command);

    expect(sitemapScreen())
        ->toContain('<h3 class="sitemapbestand-fouten-kop">Valt eruit</h3>')
        ->toContain('<li><span class="sitemapbestand-fout-naam">Zoeken</span> <span class="adres">/zoeken</span> · geblokkeerd door robots.txt</li>');
});

it('lists every page in menu order with when it changed and why it is in the sitemap or not', function (): void {
    sitemapScreenSite();
    servePages();

    $screen = sitemapScreen();

    preg_match_all('/<span class="sitemaprij-naam">(.*?)<\/span>/', $screen, $names);
    expect($names[1])->toBe(['Home', 'Onze teams', 'Merwestad 1', 'Jeugd', 'Zoeken', 'Proefles aanvragen', 'Bedankt', 'Privacy']);

    preg_match_all('/<span class="sitemaprij-reden"><span class="bol (\w+)" aria-hidden="true"><\/span>(.*?)<\/span>/', $screen, $reasons);
    expect($reasons[2])->toBe([
        'Staat erin',
        'Staat erin',
        'Staat erin',
        'Niet erin: nog niet gepubliceerd',
        'Niet erin: geblokkeerd door robots.txt',
        'Niet erin: Google mag hem niet tonen',
        'Niet erin: verborgen voor bezoekers',
        'Niet erin: uitgevinkt',
    ])->and($reasons[1])->toBe(['goed', 'goed', 'goed', 'stil', 'let', 'stil', 'stil', 'stil']);

    expect($screen)
        ->toContain('<li class="sitemaprij" id="made-cms-sitemap-')
        ->toContain('data-diepte="1"')
        ->toContain('<span class="sitemaprij-datum"><span class="sr-only">Gewijzigd </span>vandaag</span>')
        ->toContain('<span class="sitemaprij-datum"><span class="sr-only">Gewijzigd </span>14 nov</span>')
        ->toContain('<span class="sitemaprij-datum"><span class="sr-only">Gewijzigd </span>nog nooit</span>');
});

it('offers the switch only where it decides, and points elsewhere for the rest', function (): void {
    $site = sitemapScreenSite();
    servePages();

    $screen = sitemapScreen();

    expect(substr_count($screen, 'class="schakel"'))->toBe(4)
        ->and($screen)->toContain('aria-label="Privacy in de sitemap"')
        ->toContain('<a class="sitemaprij-elders" href="/made-cms/pages/'.Page::query()->where('title', 'Bedankt')->value('id').'" aria-label="Verander dit bij de pagina Bedankt">bij de pagina</a>')
        ->toContain('<span class="sitemaprij-elders">bij de regels</span>')
        ->toContain('Een vinkje slaat meteen op.');

    preg_match('/<form[^>]*action="\/made-cms\/sitemap\/'.$site['privacy']->id.'"[^>]*>.*?<\/form>/s', $screen, $form);
    expect($form[0])
        ->toContain('data-autosave')
        ->toContain('<input type="hidden" name="in_sitemap" value="0">')
        ->not->toContain(' checked')
        ->toContain('<noscript><button type="submit" class="knop stil">Opslaan</button></noscript>');
});

it('chooses between the sitemaps of more than one route group', function (): void {
    Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams']);
    servePages();
    Route::middleware('web')->prefix('nl')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });
    config(['made-cms-package.sitemap.robots' => ['*' => ['/nl/teams']]]);

    $root = sitemapScreen();
    $nl = sitemapScreen('?groep=nl');

    expect($root)
        ->toContain('<button type="submit" class="filter" name="groep" value="" aria-pressed="true">/sitemap.xml</button>')
        ->toContain('<button type="submit" class="filter" name="groep" value="nl" aria-pressed="false">/nl/sitemap.xml</button>')
        ->toContain('Staat erin')
        ->and($nl)
        ->toContain('<button type="submit" class="filter" name="groep" value="nl" aria-pressed="true">/nl/sitemap.xml</button>')
        ->toContain('<a class="standkaart-bestand" href="https://ttvmerwestad.nl/nl/sitemap.xml">ttvmerwestad.nl/nl/sitemap.xml</a>')
        ->toContain('Niet erin: geblokkeerd door robots.txt');
});

it('offers no choice of route group with one group', function (): void {
    servePages();

    expect(sitemapScreen())->not->toContain('sitemapgroepen');
});

it('says there is no sitemap yet when the site does not serve its pages through the CMS', function (): void {
    Page::factory()->published()->at('/')->create(['title' => 'Home']);

    $screen = sitemapScreen();

    expect($screen)
        ->toContain('De site toont zijn pagina’s nog niet via het CMS, dus er is nog geen sitemap.')
        ->not->toContain('Het bestand zelf')
        ->toContain('<span class="sitemaprij-naam">Home</span>')
        ->and(Sitemap::query()->exists())->toBeFalse();
});

it('never refuses its address', function (string $query): void {
    servePages();

    sitemapScreen($query);
})->with([
    'an unknown group' => ['?groep=en'],
    'a list' => ['?groep[]=nl'],
    'something else' => ['?filter=alles'],
]);

it('reads the screen in the same number of queries however many pages there are', function (): void {
    sitemapScreenSite();
    servePages();
    builtSitemap(SitemapCause::Command);
    $editor = Editor::factory()->withRoles(Role::SeoSpecialist)->create();

    $queries = 0;
    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $queries++;
    });

    $this->actingAs($editor, 'made-cms')->get('made-cms/sitemap')->assertOk();
    $few = $queries;

    Page::factory()->published()->count(12)->sequence(fn ($sequence): array => ['position' => 10 + $sequence->index])->create();
    foreach (Page::query()->doesntHave('route')->get() as $page) {
        $page->route()->create(['path' => '/extra-'.$page->id]);
    }
    builtSitemap(SitemapCause::Command);

    $queries = 0;
    $this->actingAs($editor, 'made-cms')->get('made-cms/sitemap')->assertOk();

    expect($queries)->toBe($few);
});

it('warns when the site has a sitemap.xml of its own that goes before the CMS, with what is in it and what to do', function (): void {
    sitemapScreenSite();
    servePages();
    file_put_contents(public_path('sitemap.xml'), publicSitemap(['https://ttvmerwestad.nl/', 'https://ttvmerwestad.nl/oud']));
    touch(public_path('sitemap.xml'), CarbonImmutable::parse('2024-03-12 10:00:00')->getTimestamp());

    $screen = sitemapScreen();

    expect($screen)
        ->toContain('<section class="sitemapmeldingen" aria-label="Waarschuwingen">')
        ->toContain('<strong class="sitemapmelding-titel">De site heeft een eigen sitemap.xml, en die gaat vóór het CMS</strong>')
        ->toContain('Vraagt Google /sitemap.xml op, dan krijgt hij dát bestand en niet deze sitemap. Het is voor het laatst gewijzigd op 12 maart 2024 en noemt 2 adressen; 2 pagina’s van nu ontbreken erin.')
        ->toContain('<span class="voor-developer-kop">Voor de developer</span>')
        ->toContain('<span class="voor-developer-tekst" data-copy-source>Verwijder public/sitemap.xml uit de site (of hernoem het), zodat het CMS het adres /sitemap.xml bedient. Zodra het bestand weg is, verdwijnt deze melding.</span>')
        ->toContain('<button type="button" class="knop stil" data-copy data-copied="Instructie gekopieerd" data-refused="Kopiëren lukte niet; de instructie is geselecteerd, kopieer hem zelf." hidden>Instructie kopiëren</button>')
        ->toContain('Jij hoeft hier verder niets te doen.')
        ->toContain('Waarschijnlijk door de melding hieronder.');
});

it('says a sitemap.xml of its own is no sitemap search engines can read', function (): void {
    servePages();
    file_put_contents(public_path('sitemap.xml'), 'geen xml');
    touch(public_path('sitemap.xml'), CarbonImmutable::parse('2026-11-20 10:00:00')->getTimestamp());

    expect(sitemapScreen())->toContain('Het is voor het laatst gewijzigd op 20 november en is geen sitemap die zoekmachines kunnen lezen.');
});

it('warns when the robots.txt of the site does not name the sitemap', function (): void {
    servePages();
    file_put_contents(public_path('robots.txt'), "User-agent: *\nDisallow:\n");

    $screen = sitemapScreen();

    expect($screen)
        ->toContain('<strong class="sitemapmelding-titel">De robots.txt van de site noemt de sitemap niet</strong>')
        ->toContain('Haal public/robots.txt weg zodat het CMS hem maakt, of voeg deze regel toe: Sitemap: https://ttvmerwestad.nl/sitemap.xml')
        ->not->toContain('Aanmelden hoeft niet');
});

it('does not warn about a robots.txt of the site that names the sitemap', function (): void {
    servePages();
    file_put_contents(public_path('robots.txt'), "User-agent: *\nDisallow:\n\nSitemap: https://ttvmerwestad.nl/sitemap.xml\n");

    expect(sitemapScreen())->not->toContain('sitemapmeldingen');
});

it('does not warn without files of its own', function (): void {
    servePages();

    expect(sitemapScreen())->not->toContain('sitemapmeldingen')
        ->not->toContain('Waarschijnlijk door de melding hieronder.');
});
