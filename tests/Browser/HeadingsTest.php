<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    // The hero is at its default level (H2), so the page has no H1; the title of the card is at H4, right after an H2.
    // The layout of the workbench adds a fixed H2 in its footer.
    $this->page = Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', ['kop' => 'Welkom bij de club'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [
            ['id' => '7e3a4b5c-6d7e-4f8a-8b9c-1d2e3f4a5b6c', 'titel' => ['text' => 'Trainen', 'tag' => 'h4'], 'tekst' => 'Elke dinsdag'],
        ]], id: 'cards'),
    ])->create(['title' => 'Home']);
});

/**
 * The edit shell of the page with the tab Koppen open, once the overview lists the given number of headings.
 */
function openHeadings(Page $page, int $headings): mixed
{
    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil(siteInFrame()."?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    $shell->click('[data-slot="head-tab-headings"]');

    expect($shell->script(waitUntil("document.querySelectorAll('[data-slot=\"headings-list\"] li').length === {$headings}")))->toBeTrue();

    return $shell;
}

/**
 * A script that lists the headings of the overview: level, text and source, the indentation, and whether it opens.
 */
function headingRows(): string
{
    return <<<'JS'
        () => [...document.querySelectorAll('[data-slot="headings-list"] li')].map((item) => [
            item.querySelector('[data-slot="headings-level"]').textContent,
            item.querySelector('[data-slot="headings-text"]').textContent,
            item.querySelector('[data-slot="headings-source"]').textContent,
            item.dataset.level,
            item.querySelector('button') ? 'opens' : 'fixed',
        ].join(' | '))
        JS;
}

/**
 * A JavaScript expression for the titles of the signals above the list.
 */
function headingSignals(): string
{
    return "[...document.querySelectorAll('[data-slot=\"headings-signals\"] .kopsignaal-titel')].map((title) => title.textContent)";
}

it('shows the SEO specialist the headings of the page, signals included, and follows a level chosen from a signal', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms');
    $shell = openHeadings($this->page, 4);

    $shell->assertAttribute('[data-slot="head-tab-headings"]', 'aria-selected', 'true')
        ->assertSeeIn('[data-slot="head-eyebrow"]', 'Koppen')
        ->assertSeeIn('[data-slot="head-sub"]', '4 koppen · het niveau zegt Google wat belangrijk is')
        ->assertMissing('[data-slot="structure-list"]');

    expect($shell->script(headingRows()))->toBe([
        'H2 | Welkom bij de club | Hero | 2 | opens',
        'H2 | Wat we doen | Kaarten | 2 | opens',
        'H4 | Trainen | Kaarten › Kaarten | 4 | opens',
        'H2 | Contact | vast in de site | 2 | fixed',
    ])->and($shell->script(waitUntil(headingSignals().".join() === 'Geen H1 op deze pagina,Na H2 komt meteen H4: H3 overgeslagen'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="headings-signals"] .kopsignaal:nth-child(1)', 'Google ziet dan geen hoofdonderwerp.')
        ->assertMissing('[data-slot="headings-fine"]');

    // "Open de eerste kop" opens the heading of the hero; the SEO specialist makes it the H1.
    $shell->click('[data-slot="headings-signals"] .kopsignaal:nth-child(1) [data-slot="headings-signal-action"]');

    expect($shell->script(waitUntil("document.activeElement?.closest('[data-path=\"kop\"]') && document.querySelector('[data-slot=\"structure-crumb\"]').textContent === 'Hero'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="structure-back"]', 'Koppen');
    $shell->script(markFrame());
    $shell->click('[data-path="kop"] [data-slot="level"]')
        ->click('[data-path="kop"] [role="option"]:nth-child(1)');

    expect($shell->script(waitUntil(frameReloaded().' && '.siteInFrame()."?.querySelector('[data-cms-strip=\"hero\"] h1')?.textContent === 'Welkom bij de club'")))->toBeTrue();

    $shell->click('[data-slot="structure-back"]');

    expect($shell->script(waitUntil(headingSignals().".join() === 'Na H2 komt meteen H4: H3 overgeslagen'")))->toBeTrue()
        ->and($shell->script(headingRows())[0])->toBe('H1 | Welkom bij de club | Hero | 1 | opens');

    // The skipped level opens the title of the card, in its item; H3 puts the structure in order.
    $shell->click('[data-slot="headings-signals"] .kopsignaal:nth-child(1) [data-slot="headings-signal-action"]');

    expect($shell->script(waitUntil("document.activeElement?.closest('[data-path=\"kaarten.0.titel\"]')")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="crumb-strip"]', 'Kaarten')
        ->assertSeeIn('[data-slot="crumb-item"]', 'Trainen');
    $shell->script(markFrame());
    $shell->click('[data-path="kaarten.0.titel"] [data-slot="level"]')
        ->click('[data-path="kaarten.0.titel"] [role="option"]:nth-child(3)');

    expect($shell->script(waitUntil(frameReloaded().' && '.siteInFrame()."?.querySelector('[data-cms-strip=\"cards\"] li h3')?.textContent === 'Trainen'")))->toBeTrue();

    $shell->click('[data-slot="back"]')
        ->click('[data-slot="structure-back"]');

    expect($shell->script(waitUntil(headingSignals().'.length === 0')))->toBeTrue();

    $shell->assertSeeIn('[data-slot="headings-fine"]', 'De koppen staan in orde. Eén H1, geen niveau overgeslagen, geen lege kop.')
        ->assertAttribute('[data-slot="head-tab-headings"]', 'aria-selected', 'true')
        ->assertNoJavaScriptErrors();
});

it('opens the field of a heading from the list, and goes back to the headings', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms');
    $shell = openHeadings($this->page, 4);

    $shell->assertAttribute('[data-slot="headings-list"] li:nth-child(2) button', 'aria-label', 'H2, Wat we doen, Kaarten')
        ->assertMissing('[data-slot="headings-list"] li:nth-child(4) button')
        ->click('[data-slot="headings-list"] li:nth-child(2) button');

    expect($shell->script(waitUntil("document.activeElement?.closest('[data-path=\"kop\"]') && document.querySelector('[data-slot=\"structure-crumb\"]').textContent === 'Kaarten'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="structure-back"]', 'Koppen')
        ->click('[data-slot="structure-back"]');

    expect($shell->script(waitUntil("document.activeElement === document.querySelector('[data-slot=\"headings-list\"] li:nth-child(2) button')")))->toBeTrue();

    // The opbouw tab goes back to the strips; a strip opened from there goes back to the opbouw.
    $shell->click('[data-slot="head-tab-structure"]')
        ->assertVisible('[data-slot="structure-list"]')
        ->assertMissing('[data-slot="headings"]')
        ->click('[data-strip="hero"] [data-slot="row-edit"]')
        ->assertSeeIn('[data-slot="structure-back"]', 'Opbouw')
        ->assertNoJavaScriptErrors();
});

it('shows a content manager the opbouw without the tab Koppen', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::ContentManager)->create(), 'made-cms');
    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit');

    expect($shell->script(waitUntil(siteInFrame()."?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[role=\"tablist\"], [data-slot=\"headings\"]') === null"))->toBeTrue();

    $shell->assertVisible('[data-slot="structure-list"]')
        ->assertSeeIn('[data-slot="head-eyebrow"]', 'Opbouw')
        ->assertNoJavaScriptErrors();
});
