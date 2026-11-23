<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Models\Page;

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
    $config = json_decode(htmlspecialchars_decode(str($html)->after('<ul class="boom" data-tree data-config="')->before('"')->value()), true);

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
