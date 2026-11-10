<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    $this->page = Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', [
            'kop' => 'Welkom bij de club',
            'knoppen' => [
                ['id' => '5c1e2f3a-4b5c-4d6e-8f7a-9b0c1d2e3f4a', 'knop' => ['text' => 'Lid worden', 'link' => ['url' => '/lid-worden']]],
                ['id' => '6d2f3a4b-5c6d-4e7f-9a8b-0c1d2e3f4a5b', 'knop' => ['text' => 'Meer ontdekken', 'link' => ['url' => '#over-ons']]],
            ],
        ], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kolommen' => '3', 'kaarten' => [
            ['id' => '7e3a4b5c-6d7e-4f8a-8b9c-1d2e3f4a5b6c', 'titel' => 'Trainen', 'tekst' => "Elke dinsdag\nen donderdag"],
            ['id' => '8f4b5c6d-7e8f-4a9b-9c0d-2e3f4a5b6c7d', 'titel' => 'Spelen', 'tekst' => 'Competitie'],
        ]], id: 'cards'),
    ])->create(['title' => 'Home']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript expression for the document of the site in the frame of the edit shell.
 */
function siteInFrame(): string
{
    return "document.querySelector('.bewerkframe').contentDocument";
}

/**
 * A script that marks the window in the frame: the mark is gone once the frame has reloaded.
 */
function markFrame(): string
{
    return "() => { document.querySelector('.bewerkframe').contentWindow.voorHerladen = true; }";
}

/**
 * A JavaScript condition: the frame reloaded since markFrame().
 */
function frameReloaded(): string
{
    return "! document.querySelector('.bewerkframe').contentWindow.voorHerladen";
}

/**
 * A script that clicks the site in the frame at the middle of the element, as the editor does: the click lands on
 * the outline that lies over the strip.
 */
function clickOnSite(string $selector): string
{
    $quoted = json_encode($selector, JSON_THROW_ON_ERROR);

    return <<<JS
        () => {
            const site = document.querySelector('.bewerkframe').contentDocument;
            const target = site.querySelector({$quoted});
            const strip = target.closest('[data-cms-strip]').getAttribute('data-cms-strip');
            const box = target.getBoundingClientRect();
            const outline = site.querySelector('made-cms-outlines').shadowRoot.querySelector('[data-strip="' + strip + '"]');

            outline.dispatchEvent(new site.defaultView.MouseEvent('click', { bubbles: true, composed: true, detail: 1, clientX: box.left + box.width / 2, clientY: box.top + box.height / 2 }));
        }
        JS;
}

it('patches a field with data-cms-field while the editor types, without reloading the frame', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");
    $heading = siteInFrame()."?.querySelector('[data-cms-strip=\"hero\"] h2')?.textContent";

    $shell->script(markFrame());
    $shell->type('[data-path="kop"] input', 'Samen tafeltennissen');

    // The heading follows before the draft is saved.
    expect($shell->script(waitUntil("{$heading} === 'Samen tafeltennissen' && document.querySelector('[data-slot=\"status\"]').dataset.state !== 'saved'")))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and($shell->script(valueAfter(frameReloaded(), 500)))->toBeFalse()
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe(['text' => 'Samen tafeltennissen', 'tag' => 'h2']);

    $shell->assertNoJavaScriptErrors();
});

it('patches the text and the address of a button in an item of a list', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelectorAll('[data-path=\"knoppen\"] li').length === 2");
    $button = siteInFrame()."?.querySelector('[data-cms-items=\"knoppen\"]')?.children[0]";

    $shell->script(markFrame());
    $shell->click('[data-path="knoppen"] li:nth-child(1) [data-slot="edit"]')
        ->type('[data-path="knoppen.0.knop.text"] input', 'Word lid')
        ->type('[data-path="knoppen.0.knop.link"] input', '/word-lid');

    expect($shell->script(waitUntil("{$button}?.textContent === 'Word lid' && {$button}?.getAttribute('href') === '/word-lid'")))->toBeTrue();

    $shell->click('[data-slot="done"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and($shell->script(valueAfter(frameReloaded(), 500)))->toBeFalse()
        ->and(draftOf($this->page, 'hero')['values']['knoppen'][0]['knop'] ?? null)->toBe(['text' => 'Word lid', 'link' => ['url' => '/word-lid']]);

    $shell->assertNoJavaScriptErrors();
});

it('patches the text of a heading in an item while the element is its level, and keeps the level', function (): void {
    $shell = openStrip($this->page, 'cards', "document.querySelectorAll('[data-path=\"kaarten\"] li').length === 2");
    $title = siteInFrame()."?.querySelectorAll('[data-cms-strip=\"cards\"] li')[1]?.querySelector('h3[data-cms-field=\"titel\"]')?.textContent";

    $shell->script(markFrame());
    $shell->click('[data-path="kaarten"] li:nth-child(2) [data-slot="edit"]')
        ->assertSeeIn('[data-path="kaarten.1.titel"] legend', 'Titel')
        ->type('[data-path="kaarten.1.titel.text"] input', 'Wedstrijden');

    expect($shell->script(waitUntil("{$title} === 'Wedstrijden'")))->toBeTrue();

    $shell->click('[data-slot="done"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and($shell->script(valueAfter(frameReloaded(), 500)))->toBeFalse()
        ->and(draftOf($this->page, 'cards')['values']['kaarten'][1]['titel'] ?? null)->toBe(['text' => 'Wedstrijden', 'tag' => 'h3']);

    $shell->assertNoJavaScriptErrors();
});

it('patches a long text in the form the site prints it and reloads once when that form is unknown', function (): void {
    $shell = openStrip($this->page, 'cards', "document.querySelectorAll('[data-path=\"kaarten\"] li').length === 2");
    $first = siteInFrame()."?.querySelectorAll('[data-cms-strip=\"cards\"] li')[0]?.querySelector('[data-cms-field=\"tekst\"]')";
    $second = siteInFrame()."?.querySelectorAll('[data-cms-strip=\"cards\"] li')[1]?.querySelector('[data-cms-field=\"tekst\"]')";

    $shell->script(markFrame());
    $shell->click('[data-path="kaarten"] li:nth-child(1) [data-slot="edit"]')
        ->type('[data-path="kaarten.0.tekst"] textarea', "Elke maandag\nen donderdag");

    // The view prints line breaks with nl2br, so the patch does too.
    expect($shell->script(waitUntil("{$first}?.innerHTML === 'Elke maandag<br>en donderdag'")))->toBeTrue();

    $shell->click('[data-slot="done"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and($shell->script(valueAfter(frameReloaded(), 500)))->toBeFalse();

    // A text without line breaks does not tell how the site prints them: the first one reloads the frame.
    $shell->click('[data-path="kaarten"] li:nth-child(2) [data-slot="edit"]')
        ->type('[data-path="kaarten.1.tekst"] textarea', "Competitie\nop vrijdag")
        ->click('[data-slot="done"]');

    expect($shell->script(waitUntil(frameReloaded()." && {$second}?.querySelectorAll('br').length === 1")))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('never patches an address the site would not render into a link in the frame', function (string $address): void {
    $shell = openStrip($this->page, 'hero', "document.querySelectorAll('[data-path=\"knoppen\"] li').length === 2");
    $button = siteInFrame()."?.querySelector('[data-cms-items=\"knoppen\"]')?.children[0]";

    $shell->click('[data-path="knoppen"] li:nth-child(1) [data-slot="edit"]')
        ->type('[data-path="knoppen.0.knop.link"] input', $address);

    expect($shell->script(valueAfter("{$button}?.getAttribute('href')", 500)))->toBe('/lid-worden');

    $shell->assertNoJavaScriptErrors();
})->with([
    'a script' => ['javascript:alert(1)'],
    'another site without a scheme' => ['//example.org'],
]);

it('reloads the frame at the same scroll position after a change to a field without data-cms-field', function (): void {
    $page = Page::factory()->published()->atPosition(1)->at('/lang')->withStrips([
        storedStrip('hero', ['kop' => 'Bovenaan'], id: 'boven'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kolommen' => '3', 'kaarten' => [['titel' => 'Trainen'], ['titel' => 'Spelen'], ['titel' => 'Kijken']]], id: 'cards'),
        ...array_map(static fn (int $n): array => storedStrip('hero', ['kop' => 'Onderaan '.$n], id: 'onder-'.$n), range(1, 30)),
    ])->create(['title' => 'Lang']);

    $outlines = siteInFrame()."?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning')";
    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil("{$outlines}?.length === 32")))->toBeTrue();

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="cards"] .tab'));

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"kolommen\"] select')?.value === '3'")))->toBeTrue();

    $y = $shell->script("() => { const win = document.querySelector('.bewerkframe').contentWindow; win.scrollTo(0, 200); win.voorHerladen = true; return win.scrollY; }");

    expect($y)->toBeGreaterThan(100);

    $shell->select('[data-path="kolommen"] select', '2');

    expect($shell->script(waitUntil(frameReloaded()." && {$outlines}?.length === 32 && ".siteInFrame()."?.querySelector('[data-cms-strip=\"cards\"]')?.dataset.kolommen === '2' && Math.abs(document.querySelector('.bewerkframe').contentWindow.scrollY - {$y}) < 2 && [...{$outlines}].find((outline) => outline.classList.contains('actief'))?.dataset.strip === 'cards'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="head-name"]', 'Kaarten')
        ->assertNoJavaScriptErrors();
});

it('reloads the frame when an item is added or removed', function (): void {
    $shell = openStrip($this->page, 'cards', "document.querySelectorAll('[data-path=\"kaarten\"] li').length === 2");
    $cards = siteInFrame()."?.querySelectorAll('[data-cms-strip=\"cards\"] li')";

    $shell->script(markFrame());
    $shell->click('[data-path="kaarten"] [data-slot="add"]')
        ->type('[data-path="kaarten.2.titel"] input', 'Kijken')
        ->click('[data-slot="done"]');

    expect($shell->script(waitUntil(frameReloaded()." && {$cards}?.length === 3 && {$cards}[2].querySelector('h3')?.textContent === 'Kijken'")))->toBeTrue();

    $shell->script(markFrame());
    $shell->click('[data-path="kaarten"] li:nth-child(1) [data-slot="edit"]')
        ->click('[data-slot="remove"]');

    expect($shell->script(waitUntil(frameReloaded()." && {$cards}?.length === 2 && {$cards}[0].querySelector('h3')?.textContent === 'Spelen'")))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('opens the field the editor clicks on the site in the panel', function (): void {
    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit');

    expect($shell->script(waitUntil(siteInFrame()."?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    $shell->script(clickOnSite('[data-cms-strip="hero"] h2'));

    expect($shell->script(waitUntil("document.activeElement === document.querySelector('[data-path=\"kop\"] input')")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="head-name"]', 'Hero');

    // A field in an item opens that item.
    $shell->script(clickOnSite('[data-cms-items="knoppen"] > :nth-child(2)'));

    expect($shell->script(waitUntil("document.activeElement === document.querySelector('[data-path=\"knoppen.1.knop.text\"] input')")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="crumb-item"]', 'Meer ontdekken')
        ->assertSeeIn('[data-slot="item-position"]', 'Item 2 van 2');

    // A field at the top of the strip closes the item again.
    $shell->script(clickOnSite('[data-cms-strip="hero"] h2'));

    expect($shell->script(waitUntil("document.activeElement === document.querySelector('[data-path=\"kop\"] input')")))->toBeTrue();

    // A field of another strip chooses that strip.
    $shell->script(clickOnSite('[data-cms-strip="cards"] li:nth-child(2) h3'));

    expect($shell->script(waitUntil("document.activeElement === document.querySelector('[data-path=\"kaarten.1.titel\"] input')")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="head-name"]', 'Kaarten')
        ->assertNoJavaScriptErrors();
});
