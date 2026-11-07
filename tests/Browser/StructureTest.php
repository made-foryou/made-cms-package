<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    $this->live = [
        storedStrip('hero', ['kop' => 'Welkom bij de club'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [['id' => '0a3e5b9c-1d2f-4a6b-8c7d-9e0f1a2b3c4d', 'titel' => 'Trainen']]], id: 'cards'),
    ];
    $this->page = Page::factory()->published()->at('/')->withStrips($this->live)->create(['title' => 'Home']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript expression for the outlines in the frame, in the order of the site.
 */
function frameOutlines(): string
{
    return "[...(document.querySelector('.bewerkframe')?.contentDocument?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning') ?? [])]";
}

/**
 * A JavaScript condition on the text of a slot of the shell.
 */
function slotSays(string $slot, string $text): string
{
    return "document.querySelector('[data-slot=\"{$slot}\"]')?.textContent.trim() === ".json_encode($text, JSON_THROW_ON_ERROR);
}

/**
 * The edit shell of a page with the opbouw in the panel, once the frame shows the given number of strips.
 */
function openStructure(Page $page, int $outlines): mixed
{
    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil(frameOutlines().'.length === '.$outlines)))->toBeTrue();

    return $shell;
}

/**
 * The ids of the strips of the working copy (the draft, else the published strips), in order.
 *
 * @return list<string>
 */
function draftIds(Page $page): array
{
    $stored = Page::query()->findOrFail($page->id);

    return array_column($stored->draft_strips ?? $stored->strips, 'id');
}

it('shows the opbouw of the page with every strip in order', function (): void {
    $shell = openStructure($this->page, 2);

    $shell->assertSeeIn('[data-slot="head-eyebrow"]', 'Opbouw')
        ->assertSeeIn('[data-slot="head-name"]', 'Home')
        ->assertSeeIn('[data-slot="head-sub"]', '2 stroken · Klik een strook op de site, of kies er hier een.')
        ->assertSeeIn('[data-slot="head-position"]', 'Pagina')
        ->assertSeeIn('[data-strip="hero"] [data-slot="row-sub"]', 'Welkom bij de club · 4 velden')
        ->assertSeeIn('[data-strip="cards"] [data-slot="row-sub"]', 'Wat we doen · 3 velden')
        ->assertAttribute('[data-strip="cards"] [data-slot="row-grip"]', 'aria-label', 'Kaarten, plek 2 van 2. Verplaatsen met pijltje omhoog of omlaag.')
        ->assertNoJavaScriptErrors();
});

it('adds a strip from the catalogue at the end, chooses it and opens its fields', function (): void {
    $shell = openStructure($this->page, 2);

    $shell->click('[data-slot="structure-add"]')
        ->assertSeeIn('[data-slot="catalogue-place"]', 'Kaarten · wordt strook 3 van 3')
        ->assertSeeIn('[data-slot="catalogue-types"]', 'Grote kop met een korte introductie en knoppen.')
        ->click('[data-slot="catalogue-types"] .soortkaart:first-child');

    expect($shell->script(waitUntil(slotSays('head-eyebrow', 'Nieuwe strook')." && document.querySelector('[data-path=\"kop\"] input') !== null && ".frameOutlines().'.length === 3')))->toBeTrue();

    $ids = draftIds($this->page);
    $new = $ids[2] ?? null;

    expect($ids)->toHaveCount(3)
        ->and(array_slice($ids, 0, 2))->toBe(['hero', 'cards'])
        ->and($shell->script(waitUntil(frameOutlines().".find((outline) => outline.classList.contains('actief'))?.dataset.strip === ".json_encode($new))))->toBeTrue()
        ->and($shell->script(waitUntil(slotSays('bar-state', 'Concept · 1 wijziging'))))->toBeTrue();

    $shell->assertSeeIn('[data-slot="head-name"]', 'Hero')
        ->assertSeeIn('[data-slot="head-position"]', 'Strook 3 van 3')
        ->assertSeeIn('[data-slot="added-text"]', 'Toegevoegd na Kaarten. Kop is verplicht. Dat is nu geen fout, maar "Publiceren" wacht tot het gevuld is.')
        ->assertNoJavaScriptErrors();
});

it('adds a strip between two strips through the insert point', function (): void {
    $shell = openStructure($this->page, 2);

    $shell->click('[aria-label="Strook invoegen vóór Kaarten"]')
        ->assertSeeIn('[data-slot="catalogue-place"]', 'Hero · wordt strook 2 van 3')
        ->click('[data-slot="catalogue-types"] .soortkaart:nth-child(2)');

    expect($shell->script(waitUntil(slotSays('head-position', 'Strook 2 van 3').' && '.frameOutlines().'.length === 3')))->toBeTrue();

    $draft = Page::query()->findOrFail($this->page->id)->draft_strips ?? [];

    expect(array_column($draft, 'type'))->toBe(['hero', 'cards', 'cards'])
        ->and($draft[0]['id'] ?? null)->toBe('hero')
        ->and($draft[2]['id'] ?? null)->toBe('cards');

    $shell->assertSeeIn('[data-slot="head-name"]', 'Kaarten')
        ->assertSeeIn('[data-slot="added-text"]', 'Toegevoegd na Hero.')
        ->assertNoJavaScriptErrors();
});

it('goes back to the opbouw from the catalogue without adding anything', function (): void {
    $shell = openStructure($this->page, 2);

    $shell->click('[data-slot="structure-add"]')
        ->click('[data-slot="catalogue-cancel"]')
        ->assertVisible('[data-slot="structure-list"]');

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeNull();

    $shell->assertNoJavaScriptErrors();
});

it('moves the chosen strip down and up with the arrows next to its name', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");

    $shell->assertAttribute('[data-slot="structure-up"]', 'aria-disabled', 'true')
        ->click('[data-slot="structure-down"]');

    expect($shell->script(waitUntil(slotSays('structure-move-status', 'Hero staat nu op plek 2 van 2.').' && '.frameOutlines().".map((outline) => outline.dataset.strip).join() === 'cards,hero'")))->toBeTrue()
        ->and(draftIds($this->page))->toBe(['cards', 'hero']);

    $shell->assertSeeIn('[data-slot="head-position"]', 'Strook 2 van 2')
        ->assertAttribute('[data-slot="structure-down"]', 'aria-disabled', 'true')
        ->click('[data-slot="structure-up"]');

    expect($shell->script(waitUntil(slotSays('structure-move-status', 'Hero staat nu op plek 1 van 2.'))))->toBeTrue()
        ->and(draftIds($this->page))->toBe(['hero', 'cards']);

    $shell->assertNoJavaScriptErrors();
});

it('moves a strip in the opbouw with the arrow keys on its grip, and puts it back with Escape', function (): void {
    $shell = openStructure($this->page, 2);

    $shell->keys('[data-strip="hero"] [data-slot="row-grip"]', 'ArrowDown')
        ->keys('[data-strip="hero"] [data-slot="row-grip"]', 'Escape');

    expect($shell->script("() => [...document.querySelectorAll('[data-slot=\"structure-list\"] .strookrij')].map((row) => row.dataset.strip)"))->toBe(['hero', 'cards'])
        ->and(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeNull();

    $shell->keys('[data-strip="cards"] [data-slot="row-grip"]', 'ArrowUp')
        ->keys('[data-strip="cards"] [data-slot="row-grip"]', 'Enter');

    expect($shell->script(waitUntil(slotSays('structure-status', 'Kaarten staat nu op plek 1 van 2.').' && '.frameOutlines().".map((outline) => outline.dataset.strip).join() === 'cards,hero'")))->toBeTrue()
        ->and(draftIds($this->page))->toBe(['cards', 'hero'])
        ->and($shell->script("() => document.activeElement?.getAttribute('aria-label')"))->toBe('Kaarten, plek 1 van 2. Verplaatsen met pijltje omhoog of omlaag.');

    $shell->assertNoJavaScriptErrors();
});

it('drags a strip to another place in the opbouw', function (): void {
    $shell = openStructure($this->page, 2);

    $shell->drag('[data-strip="hero"] [data-slot="row-grip"]', '[data-slot="structure-add"]');

    expect($shell->script(waitUntil(slotSays('structure-status', 'Hero staat nu op plek 2 van 2.'))))->toBeTrue()
        ->and(draftIds($this->page))->toBe(['cards', 'hero']);

    $shell->assertNoJavaScriptErrors();
});

it('removes the chosen strip and puts it back with Ongedaan maken, leaving nothing to publish', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");

    $shell->click('[data-slot="structure-remove-strip"]');

    expect($shell->script(waitUntil(slotSays('structure-status', 'Hero is verwijderd. Ongedaan maken staat bovenaan het paneel.').' && '.frameOutlines().'.length === 1')))->toBeTrue()
        ->and(draftIds($this->page))->toBe(['cards'])
        ->and($shell->script(waitUntil(slotSays('bar-state', 'Concept · 1 wijziging'))))->toBeTrue();

    $shell->assertSeeIn('[data-slot="head-eyebrow"]', 'Opbouw')
        ->assertSeeIn('[data-slot="structure-notices"]', 'Hero is verwijderd.')
        ->click('[data-slot="notice-undo"]');

    expect($shell->script(waitUntil(slotSays('structure-status', 'Hero staat terug op plek 1.').' && '.frameOutlines().'.length === 2')))->toBeTrue()
        ->and(draftIds($this->page))->toBe(['hero', 'cards'])
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe('Welkom bij de club')
        ->and($shell->script(waitUntil(slotSays('bar-state', 'Geen wijzigingen'))))->toBeTrue();

    expect($shell->script("() => document.querySelector('[data-slot=\"structure-notices\"]').children.length"))->toBe(0);

    $shell->assertNoJavaScriptErrors();
});

it('saves what the editor typed in a strip before removing it, so undoing brings it back', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");

    $shell->type('[data-path="kop"] input', 'Samen tafeltennissen')
        ->click('[data-slot="structure-remove-strip"]');

    expect($shell->script(waitUntil(slotSays('structure-status', 'Hero is verwijderd. Ongedaan maken staat bovenaan het paneel.'))))->toBeTrue();

    $shell->click('[data-slot="notice-undo"]');

    expect($shell->script(waitUntil(slotSays('structure-status', 'Hero staat terug op plek 1.'))))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe('Samen tafeltennissen');

    $shell->assertNoJavaScriptErrors();
});

it('invites to add the first strip to an empty page', function (): void {
    $page = Page::factory()->published()->at('/leeg')->create(['title' => 'Leeg']);

    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    $shell->assertSeeIn('[data-slot="structure-empty"]', 'Deze pagina is nog leeg')
        ->assertSeeIn('[data-slot="head-sub"]', 'Nog geen stroken.')
        ->click('[data-slot="structure-first"]')
        ->assertSeeIn('[data-slot="catalogue-place"]', 'Bovenaan · wordt strook 1 van 1')
        ->click('[data-slot="catalogue-types"] .soortkaart:first-child');

    expect($shell->script(waitUntil(frameOutlines().'.length === 1 && '.slotSays('head-position', 'Strook 1 van 1'))))->toBeTrue()
        ->and(Page::query()->findOrFail($page->id)->draft_strips)->toHaveCount(1);

    $shell->assertSeeIn('[data-slot="added-text"]', 'Bovenaan toegevoegd.')
        ->assertNoJavaScriptErrors();
});

it('keeps a strip of a type the site no longer has, which can only be removed', function (): void {
    $old = storedStrip('oud', ['x' => 'Sponsoren'], id: 'old');
    $page = Page::factory()->published()->at('/oud')->withStrips([$this->live[0], $old])->create();

    $shell = openStructure($page, 1);

    $shell->assertSeeIn('[data-strip="old"]', 'Niet op de site')
        ->assertSeeIn('[data-slot="structure-list"]', 'Dit soort strook ("oud") zit niet meer in de site.')
        ->assertSeeIn('[data-slot="head-sub"]', '2 stroken, 1 niet op de site')
        ->assertMissing('[data-strip="old"] [data-slot="row-grip"]')
        ->assertMissing('[data-strip="old"] [data-slot="row-edit"]')
        ->click('[data-strip="old"] [data-slot="row-remove"]');

    expect($shell->script(waitUntil(slotSays('structure-status', 'oud is verwijderd.'))))->toBeTrue()
        ->and(draftIds($page))->toBe(['hero']);

    $shell->click('[data-slot="notice-undo"]');

    // Back as the published page holds it: nothing is left to publish, so the draft fell away.
    expect($shell->script(waitUntil(slotSays('structure-status', 'oud staat terug op plek 2.'))))->toBeTrue()
        ->and(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull()
        ->and(Page::query()->findOrFail($page->id)->strips)->toBe([$this->live[0], $old]);

    $shell->assertNoJavaScriptErrors();
});
