<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    $this->page = Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', ['kop' => 'Bovenaan'], id: 'boven'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kolommen' => '3', 'kaarten' => [['titel' => 'Trainen'], ['titel' => 'Spelen'], ['titel' => 'Kijken']]], id: 'cards'),
        ...array_map(static fn (int $n): array => storedStrip('hero', ['kop' => 'Onderaan '.$n], id: 'onder-'.$n), range(1, 30)),
    ])->create(['title' => 'Lang']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * The edit shell of the long page, once the frame shows every strip.
 */
function openLongPage(Page $page): mixed
{
    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil(siteInFrame()."?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 32")))->toBeTrue();

    return $shell;
}

/**
 * A script that makes the site in the frame one rendered in the browser from its next load (see the page of the
 * workbench): its strips come after it loaded, and then it scrolls to the top by itself. It marks the frame as well.
 */
function renderedInTheBrowser(): string
{
    return "() => { sessionStorage.setItem('werkbank.laat', '1'); document.querySelector('.bewerkframe').contentWindow.voorHerladen = true; }";
}

/**
 * A JavaScript expression for how far the site in the frame is scrolled.
 */
function frameScroll(): string
{
    return "document.querySelector('.bewerkframe').contentWindow.scrollY";
}

/**
 * A JavaScript condition: the site shows the strip, in the view of the frame. A new strip without content may have no
 * height, but it has a width once the site shows it.
 */
function stripInView(string $id): string
{
    return '(() => { const box = '.siteInFrame()."?.querySelector('[data-cms-strip=\"{$id}\"]')?.getBoundingClientRect(); return !! box && box.width > 0 && box.bottom >= 0 && box.top <= document.querySelector('.bewerkframe').contentWindow.innerHeight; })()";
}

it('reloads the frame at the place of the editor on a site rendered in the browser', function (): void {
    $shell = openLongPage($this->page);

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="cards"] .tab'));

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"kolommen\"] select')?.value === '3'")))->toBeTrue();

    $shell->script(renderedInTheBrowser());
    $y = $shell->script("() => { const win = document.querySelector('.bewerkframe').contentWindow; win.scrollTo(0, 600); return win.scrollY; }");

    expect($y)->toBeGreaterThan(500);

    $shell->select('[data-path="kolommen"] select', '2');

    // The site grows after it loaded and scrolls to the top by itself; the frame stays where the editor was.
    expect($shell->script(waitUntil(frameReloaded().' && '.siteInFrame()."?.querySelector('[data-cms-strip=\"cards\"]')?.dataset.kolommen === '2'")))->toBeTrue()
        ->and($shell->script(valueAfter(frameScroll(), 600)))->toEqualWithDelta($y, 2);

    $shell->assertNoJavaScriptErrors();
});

it('reloads the frame at the place of the editor after a strip moved, on a site rendered in the browser', function (): void {
    $shell = openLongPage($this->page);

    $y = $shell->script('() => { '.siteInFrame().".querySelector('[data-cms-strip=\"onder-10\"]').scrollIntoView(); return ".frameScroll().'; }');

    expect($y)->toBeGreaterThan(500);

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="onder-10"] .tab'));

    expect($shell->script(waitUntil("document.querySelector('[data-slot=\"head-position\"]')?.textContent.trim() === 'Strook 12 van 32'")))->toBeTrue();

    $shell->script(renderedInTheBrowser());
    $shell->click('[data-slot="structure-down"]');

    // The strip went one place down and is still in view, so the frame stays where the editor was.
    expect($shell->script(waitUntil(frameReloaded().' && '.siteInFrame()."?.querySelector('[data-cms-strip=\"onder-11\"] ~ [data-cms-strip=\"onder-10\"]') !== null")))->toBeTrue()
        ->and($shell->script(valueAfter(frameScroll(), 600)))->toEqualWithDelta($y, 2)
        ->and($shell->script(stripInView('onder-10')))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('shows a strip added at the end of a long page, on a site rendered in the browser', function (): void {
    $shell = openLongPage($this->page);

    $shell->script(renderedInTheBrowser());
    $shell->click('[data-slot="structure-add"]')
        ->click(catalogueCard('Hero'));

    expect($shell->script(waitUntil(frameReloaded()." && document.querySelector('[data-slot=\"head-position\"]')?.textContent.trim() === 'Strook 33 van 33'")))->toBeTrue();

    $new = Page::query()->findOrFail($this->page->id)->draft_strips[32]['id'] ?? null;

    expect($new)->toBeString()
        ->and($shell->script(valueAfter(stripInView((string) $new), 600)))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('leaves the scrolling to the editor right after the frame reloaded', function (): void {
    $shell = openLongPage($this->page);

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="cards"] .tab'));

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"kolommen\"] select')?.value === '3'")))->toBeTrue();

    $shell->script("() => { const win = document.querySelector('.bewerkframe').contentWindow; win.scrollTo(0, 600); win.voorHerladen = true; }");
    $shell->select('[data-path="kolommen"] select', '2');

    expect($shell->script(waitUntil(frameReloaded().' && '.siteInFrame()."?.querySelector('[data-cms-strip=\"cards\"]')?.dataset.kolommen === '2' && Math.abs(".frameScroll().' - 600) < 2')))->toBeTrue();

    // The editor scrolls with the wheel at once; the frame no longer holds the former place.
    $shell->script("() => { const win = document.querySelector('.bewerkframe').contentWindow; win.dispatchEvent(new win.WheelEvent('wheel', { deltaY: -500 })); win.scrollTo(0, 100); }");

    expect($shell->script(valueAfter(frameScroll(), 400)))->toEqualWithDelta(100, 2);

    $shell->assertNoJavaScriptErrors();
});
