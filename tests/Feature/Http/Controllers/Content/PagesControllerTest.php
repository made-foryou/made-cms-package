<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
});

function pagesScreen(Role $role = Role::ContentManager): string
{
    return (string) test()->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/pages')
        ->assertOk()
        ->getContent();
}

it('opens the pages screen at its own address', function (): void {
    expect(route('made-cms-package.screens.pages', [], false))->toBe('/made-cms/pages');
});

it('opens for every role, because every role opens the pages screen', function (Role $role): void {
    expect(pagesScreen($role))->toContain('<h1 id="made-cms-titel">Pagina’s</h1>')
        ->not->toContain('wordt nog gebouwd');
})->with(Role::cases());

it('sends a guest to the login page', function (): void {
    $this->get('made-cms/pages')->assertRedirect(route('made-cms-package.login'));
});

it('is never stored by a cache, framed or indexed', function (): void {
    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('introduces the screen and links to a new page', function (): void {
    expect(pagesScreen())
        ->toContain('Alles wat je site aan pagina’s heeft, in de volgorde van het menu.')
        ->toContain('<a class="knop" href="/made-cms/pages/new">Nieuwe pagina</a>')
        ->toContain('Je site heeft nog geen pagina’s.');
});

it('lists the pages in the order of the menu, the pages under a page indented below it', function (): void {
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons']);
    Page::factory()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    $html = pagesScreen();

    expect($html)->toContain('<li class="boomrij concept" data-diepte="1" data-page="'.$team->id.'" data-parent="'.$about->id.'" data-depth="1" data-move="/made-cms/pages/'.$team->id.'/position">')
        ->and(strpos($html, '>Home</a>'))->toBeLessThan(strpos($html, '>Over ons</a>'))
        ->and(strpos($html, '>Over ons</a>'))->toBeLessThan(strpos($html, '>Team</a>'))
        ->and($html)->toContain('<span class="boomrij-pad">/over-ons/team</span>')
        ->toContain('href="/made-cms/pages/'.$team->id.'" aria-label="Overzicht van Team">Overzicht</a>')
        ->toContain('href="/made-cms/pages/'.$team->id.'/edit" aria-label="Team bewerken op de site">Bewerken op de site</a>');
});

it('gives every page but the home page a grip and the buttons that move it', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons']);

    $html = pagesScreen();
    $homeRow = str($html)->after('data-page="'.$home->id.'"')->before('</li>')->value();
    $aboutRow = str($html)->after('data-page="'.$about->id.'"')->before('</li>')->value();

    expect($html)->toContain('<li class="boomrij concept" data-page="'.$home->id.'" data-parent="" data-depth="0" data-home="">')
        ->and($homeRow)->not->toContain('class="greep"')->not->toContain('data-move-to')
        ->and($aboutRow)->toContain('<span class="greep" aria-hidden="true">')
        ->toContain('role="group" aria-label="Over ons verplaatsen"')
        ->toContain('data-move-to="up" aria-label="Over ons een plek omhoog"')
        ->toContain('data-move-to="down" aria-label="Over ons een plek omlaag"')
        ->toContain('data-move-to="indent" aria-label="Over ons onder de vorige pagina hangen"')
        ->toContain('data-move-to="outdent" aria-label="Over ons een niveau omhoog"');
});

it('tells how to move a page and hands the tree what it needs to move one', function (): void {
    Page::factory()->at('/')->create(['title' => 'Home']);

    $html = pagesScreen();
    $config = json_decode(htmlspecialchars_decode(str($html)->after('data-pages data-config="')->before('"')->value()), true);

    expect($html)->toContain('Sleep een pagina om de volgorde te veranderen; sleep hem naar rechts om hem onder een andere te hangen.')
        ->toContain('<div class="boom-meldplek" role="status" data-slot="tree-notice"></div>')
        ->toContain('<p class="sr-only" aria-live="polite" data-slot="tree-announce"></p>')
        ->toMatch('#<script type="module" src="[^"]*/pages\.js[^"]*"#')
        ->and($config)->toMatchArray(['depth' => 3])
        ->and($config['csrf_token'])->toBeString()->not->toBe('')
        ->and($config['texts'])->toHaveKeys(['moved', 'moved_under', 'moved_top', 'place_top', 'place_under', 'too_deep', 'failed', 'expired']);
});

it('tells per page what still needs doing', function (): void {
    Page::factory()->atPosition(0)->published()->withMeta(null, 'Omschrijving.')->withStrips([storedStrip()])->withDraft([])->at('/a')->create(['title' => 'Gewijzigd', 'draft_started_at' => null]);
    Page::factory()->atPosition(1)->at('/b')->create(['title' => 'Leeg']);
    Page::factory()->atPosition(2)->withDraft([storedStrip()])->at('/c')->create(['title' => 'Bijna']);
    Page::factory()->atPosition(3)->published()->withMeta(null, 'Omschrijving.')->withStrips([storedStrip()])->inMenu(false)->at('/d')->create(['title' => 'Verborgen']);

    expect(pagesScreen())
        ->toContain('<span class="status live">Live</span>')
        ->toContain('<span class="boomrij-noot">wijzigingen in concept</span>')
        ->toContain('<span class="boomrij-noot">nog leeg, nooit gepubliceerd</span>')
        ->toContain('<span class="boomrij-noot">nog niet gepubliceerd</span>')
        ->toContain('<span class="boomrij-noot">niet in het menu</span>');
});

it('names a hidden page hidden and says it is out of the menu and the sitemap, a hidden draft a draft', function (): void {
    Page::factory()->atPosition(0)->published()->hidden()->withStrips([storedStrip()])->at('/privacy')->create(['title' => 'Privacy']);
    Page::factory()->atPosition(1)->published()->hidden()->withStrips([storedStrip()])->withDraft([])->at('/bedankt')->create(['title' => 'Bedankt', 'draft_started_at' => null]);
    Page::factory()->atPosition(2)->hidden()->at('/jeugd')->create(['title' => 'Jeugd']);

    $html = pagesScreen();

    expect($html)
        ->toContain('<span class="status">Verborgen</span>
                                <span class="boomrij-noot">niet in menu en sitemap</span>')
        ->toContain('<span class="status">Verborgen</span>
                                <span class="boomrij-noot">wijzigingen in concept, niet in menu en sitemap</span>')
        ->toContain('<span class="status">Concept</span>
                                <span class="boomrij-noot">nog leeg, nooit gepubliceerd, verborgen</span>')
        ->and(substr_count($html, '<span class="status">Verborgen</span>'))->toBe(2);
});

it('lists the deleted pages with the address they get back', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    DeletePage::run($page);

    expect(pagesScreen())
        ->toContain('Verwijderde pagina’s')
        ->toContain('Tot 30 dagen na het verwijderen zet je een pagina terug')
        ->toContain('action="/made-cms/pages/'.$page->id.'/restore"')
        ->toContain('/over-ons');
});

it('leaves out the deleted pages when there are none', function (): void {
    expect(pagesScreen())->not->toContain('Verwijderde pagina’s');
});

it('lists the overview and the pages under the site in the menu, the overview as current', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home']);

    $html = pagesScreen();

    expect($html)->toContain('href="/made-cms/pages" aria-current="page"')
        ->toContain('href="/made-cms/pages/'.$home->id.'"><span class="merk" aria-hidden="true"></span>Home</a>')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1);
});

it('speaks English when the launcher locale is English', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);
    Page::factory()->at('/')->create(['title' => 'Home']);

    expect(pagesScreen())->toContain('<h1 id="made-cms-titel">Pages</h1>')
        ->toContain('New page')
        ->toContain('Order of the menu')
        ->toContain('<span class="status">Draft</span>');
});

describe('the row of a page', function (): void {
    beforeEach(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-23 14:00:00'));
    });

    it('marks a page that asks for attention with a dot and says what first', function (): void {
        Page::factory()->published()->withStrips([storedStrip()])->inMenu(false)->at('/teams')->create(['title' => 'Onze teams']);

        expect(pagesScreen())->toContain('<span class="boomrij-noot"><span class="boomrij-stip" aria-hidden="true"></span><span class="sr-only">Vraagt aandacht: </span>omschrijving leeg, niet in het menu</span>');
    });

    it('says on the row that the description is longer than Google shows', function (): void {
        Page::factory()->published()->withStrips([storedStrip()])->withMeta(null, str_repeat('a', 156))->at('/teams')->create(['title' => 'Onze teams']);

        expect(pagesScreen())->toContain('<span class="sr-only">Vraagt aandacht: </span>omschrijving te lang</span>')
            ->toContain('<span class="standkaart-sub">omschrijving te lang</span>');
    });

    it('says on the row how many times the strips give an h1', function (): void {
        app()->instance(StripTypes::class, new StripTypes([new InlineStripType('kop', [Heading::make('kop')->tag('h1')], 'Kop')]));
        Page::factory()->published()->withMeta(null, 'Alle teams.')->at('/teams')
            ->withStrips([storedStrip('kop', ['kop' => 'Onze teams']), storedStrip('kop', ['kop' => 'Jeugd'])])
            ->create(['title' => 'Onze teams']);

        expect(pagesScreen())->toContain('<span class="sr-only">Vraagt aandacht: </span>2 keer een H1</span>')
            ->toContain('<span class="standkaart-sub">meer dan één H1</span>');
    });

    it('says on the row how many photos have no alt text', function (): void {
        useStripTypes();
        Storage::fake('public', ['url' => 'https://cdn.example.com']);
        $page = Page::factory()->published()->withMeta(null, 'Alle teams.')->at('/teams')
            ->withStrips([storedStrip('hero', ['foto' => Media::factory()->create()->id]), storedStrip('hero', ['foto' => Media::factory()->create()->id])])
            ->create(['title' => 'Onze teams']);
        SyncMediaUsage::run($page);

        expect(pagesScreen())->toContain('<span class="sr-only">Vraagt aandacht: </span>2 foto’s zonder alt-tekst</span>')
            ->toContain('<span class="standkaart-sub">foto’s zonder alt-tekst</span>');
    });

    it('gives no dot to a page that asks for nothing, nor to one that is only empty', function (): void {
        Page::factory()->published()->withMeta(null, 'Welkom.')->withStrips([storedStrip()])->at('/')->create(['title' => 'Home']);
        Page::factory()->atPosition(1)->at('/jeugd')->create(['title' => 'Jeugd']);

        expect(pagesScreen())->not->toContain('boomrij-stip')
            ->toContain('<span class="boomrij-noot">nog leeg, nooit gepubliceerd</span>');
    });

    it('says since when a published page has a draft', function (): void {
        Page::factory()->published()->withMeta(null, 'Wie we zijn.')->withStrips([storedStrip()])->withDraft([])
            ->draftSince(CarbonImmutable::parse('2026-11-22 16:12:00'))
            ->at('/over-ons')->create(['title' => 'Over ons']);

        expect(pagesScreen())->toContain('<span class="boomrij-noot">concept sinds gisteren</span>');
    });

    it('says how long a draft that stays open too long is open, instead of since when', function (): void {
        Page::factory()->published()->withMeta(null, 'Wie we zijn.')->withStrips([storedStrip()])->withDraft([])
            ->draftSince(CarbonImmutable::parse('2026-11-17 09:00:00'))
            ->at('/over-ons')->create(['title' => 'Over ons']);

        expect(pagesScreen())->toContain('<span class="sr-only">Vraagt aandacht: </span>concept staat al 6 dagen open</span>')
            ->not->toContain('concept sinds');
    });

    it('says there are changes in the draft when it is not known since when', function (): void {
        Page::factory()->published()->withMeta(null, 'Wie we zijn.')->withStrips([storedStrip()])->create(['title' => 'Over ons', 'draft_strips' => []]);

        expect(pagesScreen())->toContain('<span class="boomrij-noot">wijzigingen in concept</span>');
    });

    it('says when a page changed last and who did it', function (): void {
        $editor = Editor::factory()->create(['name' => 'Menno']);
        Page::factory()->at('/')->create(['updated_at' => CarbonImmutable::parse('2026-10-20 10:00:00'), 'updated_by' => $editor->id]);
        Page::factory()->atPosition(1)->at('/jeugd')->create(['updated_at' => CarbonImmutable::parse('2026-11-23 09:00:00')]);
        Page::factory()->atPosition(2)->at('/privacy')->create(['updated_at' => CarbonImmutable::parse('2024-03-12 10:00:00')]);

        expect(pagesScreen())
            ->toContain('<span class="boomrij-datum"><span class="sr-only">gewijzigd </span>20 okt · Menno</span>')
            ->toContain('<span class="boomrij-datum"><span class="sr-only">gewijzigd </span>vandaag</span>')
            ->toContain('<span class="boomrij-datum"><span class="sr-only">gewijzigd </span>12 mrt 2024</span>');
    });

    it('leaves out the name of an editor who is gone', function (): void {
        $editor = Editor::factory()->create(['name' => 'Muriël']);
        Page::factory()->at('/')->create(['updated_at' => CarbonImmutable::parse('2026-10-20 10:00:00'), 'updated_by' => $editor->id]);
        $editor->delete();

        expect(pagesScreen())->toContain('<span class="sr-only">gewijzigd </span>20 okt</span>')
            ->not->toContain('Muriël');
    });
});

describe('the state of the site', function (): void {
    /**
     * The four cards of the screen as their number, text and line under it.
     *
     * @return list<string>
     */
    function stateCards(string $html): array
    {
        preg_match_all('~<div class="standkaart[^"]*">\s*<span class="standkaart-getal">(.*?)</span>\s*<span class="standkaart-tekst">(.*?)</span>\s*<span class="standkaart-sub">(.*?)</span>~s', $html, $cards, PREG_SET_ORDER);

        return array_map(static fn (array $card): string => html_entity_decode($card[1].' '.$card[2].' · '.$card[3], ENT_QUOTES), $cards);
    }

    it('counts the live pages, the drafts, the pages that ask for attention and the hidden pages', function (): void {
        $home = Page::factory()->atPosition(0)->published()->withMeta(null, 'Welkom.')->withStrips([storedStrip()])->at('/')->create(['title' => 'Home']);
        Page::factory()->atPosition(1)->published()->withMeta(null, 'Wie we zijn.')->withStrips([storedStrip()])->withDraft([])->at('/over-ons')->create(['title' => 'Over ons']);
        Page::factory()->atPosition(2)->published()->withMeta('Tafeltennis in Dordrecht: alle teams van jong tot oud, van de jeugd tot de eredivisie')->withStrips([storedStrip()])->at('/teams')->create(['title' => 'Onze teams']);
        Page::factory()->atPosition(3)->published()->withStrips([storedStrip()])->inMenu(false)->at('/locatie')->create(['title' => 'Locatie']);
        Page::factory()->atPosition(4)->at('/jeugd')->create(['title' => 'Jeugd']);
        Page::factory()->atPosition(5)->published()->hidden()->withStrips([storedStrip()])->at('/bedankt')->create(['title' => 'Bedankt']);
        Page::factory()->atPosition(6)->published()->hidden()->withStrips([storedStrip()])->at('/privacy')->create(['title' => 'Privacy']);

        expect(stateCards(pagesScreen()))->toBe([
            '4 pagina’s live · waarvan 3 in het menu',
            '2 met wijzigingen in concept · Over ons en Jeugd',
            '2 vragen aandacht · omschrijving leeg en titel te lang',
            '2 verborgen voor bezoekers · Bedankt en Privacy, bewust buiten menu en sitemap',
        ]);
    });

    it('names three pages and counts the others', function (): void {
        foreach (['Een', 'Twee', 'Drie', 'Vier', 'Vijf'] as $position => $title) {
            Page::factory()->atPosition($position)->at('/'.strtolower($title))->create(['title' => $title]);
        }

        expect(stateCards(pagesScreen())[1])->toBe('5 met wijzigingen in concept · Een, Twee, Drie en 2 andere');
    });

    it('says when there is nothing to count', function (): void {
        Page::factory()->published()->withMeta(null, 'Welkom.')->withStrips([storedStrip()])->at('/')->create(['title' => 'Home']);

        expect(stateCards(pagesScreen()))->toBe([
            '1 pagina live · waarvan 1 in het menu',
            '0 met wijzigingen in concept · alles is gepubliceerd',
            '0 vragen aandacht · alles staat er goed voor',
            '0 verborgen voor bezoekers · alles is zichtbaar',
        ]);
    });

    it('shows no cards on a site without pages', function (): void {
        expect(pagesScreen())->not->toContain('standkaart');
    });
});

describe('searching and filtering', function (): void {
    beforeEach(function (): void {
        $teams = Page::factory()->atPosition(0)->published()->withMeta(null, 'Alle teams.')->withStrips([storedStrip()])->at('/teams')->create(['title' => 'Onze teams']);
        Page::factory()->under($teams)->atPosition(0)->published()->withMeta(null, 'Het eerste.')->withStrips([storedStrip()])->at('/teams/merwestad-1')->create(['title' => 'Merwestad 1']);
        Page::factory()->atPosition(1)->published()->withMeta(null, 'Wie we zijn.')->withStrips([storedStrip()])->withDraft([])->at('/over-ons')->create(['title' => 'Over ons']);
        Page::factory()->atPosition(2)->at('/jeugd')->create(['title' => 'Jeugd']);
        Page::factory()->atPosition(3)->published()->withStrips([storedStrip()])->at('/locatie')->create(['title' => 'Locatie']);
        Page::factory()->atPosition(4)->published()->hidden()->withStrips([storedStrip()])->at('/privacy')->create(['title' => 'Privacy']);
    });

    /**
     * The pages screen at an address of its own, as the editor of a role sees it.
     */
    function pagesScreenAt(string $query): string
    {
        return (string) test()->actingAs(Editor::factory()->create(), 'made-cms')
            ->get('made-cms/pages'.$query)
            ->assertOk()
            ->getContent();
    }

    /**
     * The names in the tree, a page that only holds the place of a match marked with brackets.
     *
     * @return list<string>
     */
    function treeNames(string $html): array
    {
        preg_match_all('~<li class="boomrij([^"]*)"[^>]*>.*?<a class="boomrij-naam"[^>]*>(.*?)</a>~s', $html, $rows, PREG_SET_ORDER);

        return array_map(static fn (array $row): string => str_contains($row[1], 'ouder') ? '('.$row[2].')' : $row[2], $rows);
    }

    it('offers a search field and the filters with the number of pages of each', function (): void {
        $html = pagesScreenAt('');

        expect($html)
            ->toContain('<label class="sr-only" for="made-cms-pagina-zoek">Zoek een pagina</label>')
            ->toContain('placeholder="Zoek op naam of adres"')
            ->toContain('<button type="submit" class="filter" name="filter" value="all" aria-pressed="true" data-filter="all">Alle <span>6</span></button>')
            ->toContain('<button type="submit" class="filter" name="filter" value="live" aria-pressed="false" data-filter="live">Live <span>4</span></button>')
            ->toContain('<button type="submit" class="filter" name="filter" value="draft" aria-pressed="false" data-filter="draft">Concept <span>2</span></button>')
            ->toContain('<button type="submit" class="filter" name="filter" value="attention" aria-pressed="false" data-filter="attention"><span class="filter-bol" aria-hidden="true"></span>Vraagt aandacht <span>1</span></button>')
            ->toContain('<button type="submit" class="filter" name="filter" value="hidden" aria-pressed="false" data-filter="hidden">Verborgen <span>1</span></button>');
    });

    it('shows only the pages with something to publish for the filter Concept', function (): void {
        $html = pagesScreenAt('?filter=draft');

        expect(treeNames($html))->toBe(['Over ons', 'Jeugd'])
            ->and($html)->toContain('value="draft" aria-pressed="true"')
            ->toContain('<input type="hidden" name="filter" value="draft">');
    });

    it('shows a page found by its address with the page above it, which only holds its place', function (): void {
        expect(treeNames(pagesScreenAt('?q=MERWESTAD')))->toBe(['(Onze teams)', 'Merwestad 1']);
    });

    it('keeps the search in its field and along with the filters', function (): void {
        $html = pagesScreenAt('?q=team&filter=live');

        expect($html)->toContain('value="team"')
            ->toContain('<input type="hidden" name="q" value="team">')
            ->and(treeNames($html))->toBe(['Onze teams', 'Merwestad 1']);
    });

    it('counts the filters over the whole site while it searches', function (): void {
        expect(pagesScreenAt('?q=jeugd'))->toContain('data-filter="all">Alle <span>6</span>')
            ->toContain('data-filter="live">Live <span>4</span>');
    });

    it('moves nothing in a part of the tree and says how to move again', function (): void {
        $html = pagesScreenAt('?filter=live');

        expect($html)->not->toContain('data-move=')
            ->not->toContain('class="greep"')
            ->not->toContain('data-move-to=')
            ->not->toContain('Sleep een pagina')
            ->toContain('Je ziet een deel van de pagina’s. <a href="/made-cms/pages">Toon alle pagina’s</a> om ze te verplaatsen.');
    });

    it('says when nothing is found', function (): void {
        $html = pagesScreenAt('?q=zwembad');

        expect(treeNames($html))->toBe([])
            ->and($html)->toContain('Geen pagina gevonden voor “zwembad”. <a href="/made-cms/pages">Toon alle pagina’s</a>')
            ->not->toContain('Je site heeft nog geen pagina’s');
    });

    it('tells a screen reader how many pages it shows', function (): void {
        expect(pagesScreenAt('?filter=draft'))->toContain('<p class="sr-only" data-slot="pages-found">2 pagina’s gevonden</p>');
    });

    it('never refuses its address: what it does not know is left out', function (string $query): void {
        expect(treeNames(pagesScreenAt($query)))->toHaveCount(6);
    })->with([
        'lists' => ['?q[]=team&filter[]=draft'],
        'unknown filter' => ['?filter=zwembad'],
        'blank search' => ['?q=%20%20'],
    ]);

    it('asks the database the same, however many pages the site has', function (): void {
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries++;
        });
        $count = function () use (&$queries): int {
            $queries = 0;
            pagesScreenAt('');

            return $queries;
        };

        Page::query()->where('title', 'Over ons')->update(['updated_by' => Editor::factory()->create()->id]);
        $few = $count();
        Page::factory()->count(12)->sequence(fn ($sequence) => ['position' => 10 + $sequence->index])
            ->published()->withStrips([storedStrip()])
            ->state(fn () => ['updated_by' => Editor::factory()->create()->id])
            ->create()
            ->each(fn (Page $page) => $page->route()->create(['path' => '/extra-'.$page->id]));

        expect($count())->toBe($few);
    });
});

it('says that news items are not in the list, with the way to them for whoever opens them', function (): void {
    Page::factory()->at('/')->create(['title' => 'Home']);

    expect(pagesScreen(Role::ContentManager))
        ->toContain('<p class="paginanieuws">Nieuwsberichten staan niet in deze lijst: die horen bij de verzameling Nieuws en krijgen vanzelf hun plek op de site. <a href="/made-cms/news">Naar Nieuws</a></p>')
        ->and(pagesScreen(Role::SeoSpecialist))
        ->toContain('<p class="paginanieuws">Nieuwsberichten staan niet in deze lijst: die horen bij de verzameling Nieuws en krijgen vanzelf hun plek op de site.</p>');
});
