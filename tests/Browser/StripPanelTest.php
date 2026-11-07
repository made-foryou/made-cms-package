<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

const KNOP_LID = '0a3e5b9c-1d2f-4a6b-8c7d-9e0f1a2b3c4d';
const KNOP_MEER = '1b4f6c0d-2e3a-4b7c-9d8e-0f1a2b3c4d5e';

beforeEach(function (): void {
    useWorkbenchSite();

    $this->page = Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', [
            'kop' => 'Welkom bij de club',
            'knoppen' => [
                ['id' => KNOP_LID, 'knop' => ['text' => 'Lid worden', 'link' => ['url' => '/lid-worden']]],
                ['id' => KNOP_MEER, 'knop' => ['text' => 'Meer ontdekken', 'link' => ['url' => '#over-ons']]],
            ],
        ], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [['id' => KNOP_LID, 'titel' => 'Trainen']]], id: 'cards'),
    ])->create(['title' => 'Home']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('builds the fields of the chosen strip and saves what the editor types as the draft', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");

    $shell->assertSeeIn('[data-path="label"]', 'Korte regel boven de kop.')
        ->assertSeeIn('[data-path="knoppen"]', '2 van maximaal 2')
        ->assertSeeIn('[data-path="knoppen"] li:nth-child(1)', 'Lid worden')
        ->type('[data-path="kop"] input', 'Samen tafeltennissen');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and($shell->script(waitUntil("document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"hero\"] h2')?.textContent === 'Samen tafeltennissen'")))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe('Samen tafeltennissen')
        ->and(Page::query()->findOrFail($this->page->id)->strips[0]['values']['kop'] ?? null)->toBe('Welkom bij de club');

    $shell->assertNoJavaScriptErrors();
});

it('opens an item of a list with a breadcrumb and saves it when the editor is done', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelectorAll('[data-path=\"knoppen\"] li').length === 2");

    $shell->click('[data-path="knoppen"] li:nth-child(1) [data-slot="edit"]')
        ->assertSeeIn('[data-slot="crumb-strip"]', 'Hero')
        ->assertSeeIn('[data-slot="crumb-list"]', 'Knoppen')
        ->assertSeeIn('[data-slot="crumb-item"]', 'Lid worden')
        ->assertSeeIn('[data-slot="item-position"]', 'Item 1 van 2')
        ->type('[data-path="knoppen.0.knop.text"] input', 'Word lid')
        ->assertSeeIn('[data-slot="crumb-item"]', 'Word lid')
        ->click('[data-slot="done"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue();

    $shell->assertSeeIn('[data-path="knoppen"] li:nth-child(1)', 'Word lid');

    expect(draftOf($this->page, 'hero')['values']['knoppen'][0] ?? null)->toBe([
        'id' => KNOP_LID,
        'knop' => ['text' => 'Word lid', 'link' => ['url' => '/lid-worden']],
    ]);

    $shell->assertNoJavaScriptErrors();
});

it('moves an item with the arrow keys on its grip', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelectorAll('[data-path=\"knoppen\"] li').length === 2");

    $shell->keys('[data-path="knoppen"] li:nth-child(1) [data-slot="grip"]', 'ArrowDown');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(array_column(draftOf($this->page, 'hero')['values']['knoppen'] ?? [], 'id'))->toBe([KNOP_MEER, KNOP_LID])
        ->and($shell->script("() => document.activeElement?.getAttribute('aria-label')"))->toBe('Verplaats Lid worden');

    $shell->assertSeeIn('[data-slot="announce"]', 'Lid worden staat nu op plek 2 van 2.')
        ->assertNoJavaScriptErrors();
});

it('hides a strip on the site and lets the editor show it again', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('#paneel-zichtbaar')?.checked === true");

    $shell->uncheck('#paneel-zichtbaar');

    expect($shell->script(waitUntil("! document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"hero\"]') && document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"cards\"]')")))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['visible'] ?? null)->toBeFalse();

    $shell->assertSeeIn('[data-slot="head-position"]', 'Strook 1 van 2 · Verborgen');

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="cards"]'));

    // A hidden strip is not on the site: the opbouw lists it with its label, and "Bewerken" chooses it.
    $shell->assertSeeIn('[data-slot="head-name"]', 'Kaarten')
        ->click('[data-slot="structure-back"]')
        ->assertSeeIn('[data-strip="hero"] [data-slot="row-hidden"]', 'Verborgen')
        ->click('[data-strip="hero"] [data-slot="row-edit"]')
        ->assertSeeIn('[data-slot="head-name"]', 'Hero')
        ->check('#paneel-zichtbaar');

    expect($shell->script(waitUntil("document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"hero\"]') !== null")))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['visible'] ?? null)->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('shows why a value is refused once the editor leaves the field', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");

    $shell->type('[data-path="kop"] input', '')
        ->click('[data-path="label"] input');

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"kop\"] [data-slot=\"error\"]')?.textContent === 'Vul Kop in.'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="status"]', 'Niet opgeslagen: 1 veld klopt nog niet.')
        ->assertAttribute('[data-path="kop"] input', 'aria-invalid', 'true');

    expect(draftOf($this->page, 'hero'))->toBe([]);

    $shell->assertNoJavaScriptErrors();
});

it('makes text bold with the toolbar of a rich text field', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"intro\"] [contenteditable]') !== null");

    $shell->type('[data-path="intro"] [contenteditable]', 'Wij spelen');
    $shell->script("() => { const editor = document.querySelector('[data-path=\"intro\"] [contenteditable]'); editor.focus(); const range = document.createRange(); range.selectNodeContents(editor); const selection = getSelection(); selection.removeAllRanges(); selection.addRange(range); }");
    $shell->click('[data-path="intro"] [data-command="bold"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['intro'] ?? null)->toBe('<p><strong>Wij spelen</strong></p>');

    $shell->assertAttribute('[data-path="intro"] [data-command="bold"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

it('adds an item that links to a page of the site', function (): void {
    $about = Page::factory()->published()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons']);

    $shell = openStrip($this->page, 'cards', "document.querySelectorAll('[data-path=\"kaarten\"] li').length === 1");

    $shell->click('[data-path="kaarten"] [data-slot="add"]')
        ->assertSeeIn('[data-slot="item-position"]', 'Item 2 van 2')
        ->type('[data-path="kaarten.1.titel"] input', 'Over de club')
        ->type('[data-path="kaarten.1.link"] input', 'Over');

    expect($shell->script(waitUntil("[...document.querySelectorAll('[data-path=\"kaarten.1.link\"] [role=option]')].some((option) => option.textContent.includes('Over ons'))")))->toBeTrue();

    $shell->click('[data-path="kaarten.1.link"] [role="option"]')
        ->assertSeeIn('[data-path="kaarten.1.link"]', 'Pagina van je site: Over ons (/over-ons)')
        ->click('[data-slot="done"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue();

    $kaarten = draftOf($this->page, 'cards')['values']['kaarten'] ?? [];

    expect($kaarten[1]['titel'] ?? null)->toBe('Over de club')
        ->and($kaarten[1]['link'] ?? null)->toBe(['page' => $about->id])
        ->and($kaarten[1]['id'] ?? null)->toBeString();

    $shell->assertNoJavaScriptErrors();
});

it('undoes the saved changes of a strip one step at a time', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");
    $heading = "document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"hero\"] h2')?.textContent";

    expect($shell->script("() => document.querySelector('[data-slot=\"undo\"]').disabled"))->toBeTrue();

    $shell->type('[data-path="kop"] input', 'Samen');

    expect($shell->script(waitUntil(savedAsDraft()." && {$heading} === 'Samen'")))->toBeTrue();

    $shell->type('[data-path="kop"] input', 'Samen tafeltennissen');

    expect($shell->script(waitUntil("{$heading} === 'Samen tafeltennissen' && document.querySelector('[data-slot=\"bar-state\"]').textContent === 'Concept · 1 wijziging'")))->toBeTrue();

    $shell->click('[data-slot="undo"]');

    expect($shell->script(waitUntil("{$heading} === 'Samen' && document.querySelector('[data-path=\"kop\"] input').value === 'Samen'")))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe('Samen');

    $shell->assertSeeIn('[data-slot="announce"]', 'De laatste wijziging is ongedaan gemaakt.')
        ->click('[data-slot="undo"]');

    expect($shell->script(waitUntil("{$heading} === 'Welkom bij de club' && document.querySelector('[data-slot=\"bar-state\"]').textContent === 'Geen wijzigingen'")))->toBeTrue()
        ->and($shell->script("() => [document.querySelector('[data-path=\"kop\"] input').value, document.querySelector('[data-slot=\"undo\"]').disabled]"))->toBe(['Welkom bij de club', true])
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe('Welkom bij de club');

    $shell->assertNoJavaScriptErrors();
});

it('never puts markup the package did not clean into the site in the frame when it undoes a change', function (): void {
    $this->page->update(['draft_strips' => [
        storedStrip('hero', ['kop' => 'Welkom bij de club', 'intro' => '<p>Welkom</p><img src="x" onerror="parent.document.body.dataset.geschreven = \'ja\'">'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen'], id: 'cards'),
    ]]);
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"intro\"] [contenteditable]')?.textContent === 'Welkom'");
    $intro = "document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"hero\"] [data-cms-field=\"intro\"]')?.innerHTML";

    $shell->script("() => { const editor = document.querySelector('[data-path=\"intro\"] [contenteditable]'); editor.innerHTML = '<p>Welkom terug</p>'; editor.dispatchEvent(new Event('input', { bubbles: true })); document.querySelector('[data-slot=\"undo\"]').click(); }");

    expect($shell->script(valueAfter("[{$intro}, document.body.dataset.geschreven ?? null]", 900)))->toBe(['<p>Welkom</p>', null]);

    $shell->assertNoJavaScriptErrors();
});

it('undoes hiding a strip', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('#paneel-zichtbaar')?.checked === true");

    $shell->uncheck('#paneel-zichtbaar');

    expect($shell->script(waitUntil("! document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"hero\"]') && ! document.querySelector('[data-slot=\"undo\"]').disabled")))->toBeTrue();

    $shell->click('[data-slot="undo"]');

    expect($shell->script(waitUntil("document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"hero\"]') !== null && document.querySelector('#paneel-zichtbaar').checked")))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['visible'] ?? null)->toBeTrue();

    $shell->assertSeeIn('[data-slot="head-position"]', 'Strook 1 van 2')
        ->assertNoJavaScriptErrors();
});

it('throws away what was typed but not saved yet', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");

    $shell->script("() => { const input = document.querySelector('[data-path=\"kop\"] input'); input.value = 'Half getypt'; input.dispatchEvent(new Event('input', { bubbles: true })); document.querySelector('[data-slot=\"undo\"]').click(); }");

    expect($shell->script(valueAfter("[document.querySelector('[data-path=\"kop\"] input').value, document.querySelector('[data-slot=\"status\"]').textContent]", 900)))->toBe(['Welkom bij de club', ''])
        ->and(draftOf($this->page, 'hero'))->toBe([]);

    $shell->assertNoJavaScriptErrors();
});
