<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);

    $this->home = Page::factory()->published()->withMeta(null, 'Welkom.')->withStrips([storedStrip()])->atPosition(0)->at('/')->create(['title' => 'Home', 'slug' => '']);
    $this->about = Page::factory()->published()->withMeta(null, 'Wie we zijn.')->withStrips([storedStrip()])->withDraft([])->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    $this->team = Page::factory()->published()->withMeta(null, 'Onze teams.')->withStrips([storedStrip()])->atPosition(2)->at('/team')->create(['title' => 'Team', 'slug' => 'team']);
    $this->youth = Page::factory()->under($this->team)->at('/team/jeugd')->create(['title' => 'Jeugd', 'slug' => 'jeugd']);
    $this->contact = Page::factory()->published()->withMeta(null, 'Bel ons.')->withStrips([storedStrip()])->atPosition(3)->at('/contact')->create(['title' => 'Contact', 'slug' => 'contact']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/** The rows of the list in the browser, a page that only holds a place in brackets. */
function overviewRows(): string
{
    return "() => [...document.querySelectorAll('[data-tree] > li.boomrij')].map((row) => (row.classList.contains('ouder') ? '(' : '') + row.querySelector('.boomrij-naam').textContent.trim() + (row.classList.contains('ouder') ? ')' : '')).join(',')";
}

function rowsAre(string $rows): string
{
    return waitUntil("[...document.querySelectorAll('[data-tree] > li.boomrij')].map((row) => (row.classList.contains('ouder') ? '(' : '') + row.querySelector('.boomrij-naam').textContent.trim() + (row.classList.contains('ouder') ? ')' : '')).join(',') === ".json_encode($rows, JSON_THROW_ON_ERROR));
}

it('shows only the pages with something to publish for the filter Concept, and keeps the focus on the filter', function (): void {
    $screen = visit('/made-cms/pages')->resize(1280, 1600);

    $screen->click('[data-filter="draft"]');

    expect($screen->script(rowsAre('Over ons,(Team),Jeugd')))->toBeTrue()
        ->and($screen->script('() => document.activeElement.dataset.filter'))->toBe('draft')
        ->and($screen->script('() => document.querySelector(\'[data-filter="draft"]\').getAttribute("aria-pressed")'))->toBe('true')
        ->and($screen->script('() => window.location.search'))->toBe('?filter=draft')
        ->and($screen->script('() => document.querySelectorAll("[data-tree] .greep").length'))->toBe(0)
        ->and($screen->script(announcedOverview('2 pagina’s gevonden')))->toBeTrue();

    $screen->assertNoJavaScriptErrors();
});

it('searches while the editor types, and keeps the text and the focus in the field', function (): void {
    $screen = visit('/made-cms/pages')->resize(1280, 1600);

    $screen->type('#made-cms-pagina-zoek', 'jeugd');

    expect($screen->script(rowsAre('(Team),Jeugd')))->toBeTrue()
        ->and($screen->script('() => document.activeElement.id'))->toBe('made-cms-pagina-zoek')
        ->and($screen->script('() => document.querySelector("#made-cms-pagina-zoek").value'))->toBe('jeugd')
        ->and($screen->script('() => window.location.search'))->toBe('?q=jeugd');

    $screen->assertNoJavaScriptErrors();
});

it('says when nothing is found', function (): void {
    $screen = visit('/made-cms/pages')->resize(1280, 1600);

    $screen->type('#made-cms-pagina-zoek', 'zwembad');

    expect($screen->script(waitUntil("document.querySelector('[data-swap=\"list\"] .leeg')?.textContent.includes('Geen pagina gevonden voor “zwembad”.') === true")))->toBeTrue();

    $screen->assertNoJavaScriptErrors();
});

it('moves pages again once it shows all of them', function (): void {
    $screen = visit('/made-cms/pages?filter=live')->resize(1280, 1600);

    expect($screen->script('() => document.querySelectorAll("[data-tree] [data-move-to]").length'))->toBe(0);

    $screen->click('[data-filter="all"]');

    expect($screen->script(rowsAre('Home,Over ons,Team,Jeugd,Contact')))->toBeTrue()
        ->and($screen->script('() => window.location.search'))->toBe('');

    $screen->keys('[data-page="'.$this->about->id.'"] [data-move-to="down"]', 'Enter');

    expect($screen->script(announcedOverview('Over ons staat nu op plek 3 van 4.')))->toBeTrue()
        ->and(Page::query()->roots()->inMenuOrder()->pluck('title')->all())->toBe(['Home', 'Team', 'Over ons', 'Contact']);

    $screen->assertNoJavaScriptErrors();
});

function announcedOverview(string $text): string
{
    return waitUntil("document.querySelector('[data-slot=\"tree-announce\"]').textContent === ".json_encode($text, JSON_THROW_ON_ERROR));
}
