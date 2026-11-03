<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    $this->page = Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', ['kop' => 'Welkom bij de club'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [['titel' => 'Trainen']]], id: 'cards'),
    ])->create(['title' => 'Home']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript expression for the outlines the edit shell drew in the frame.
 */
function outlines(): string
{
    return "[...(document.querySelector('.bewerkframe')?.contentDocument?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning') ?? [])]";
}

it('outlines every strip on the site and chooses the strip that is clicked, also after the frame reloads', function (): void {
    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit');

    expect($shell->script(waitUntil(outlines().'.length === 2')))->toBeTrue();

    // Each outline lies exactly over its strip and names its type and fields.
    expect($shell->script('() => '.outlines().".map((outline) => {
        const strip = document.querySelector('.bewerkframe').contentDocument.querySelector('[data-cms-strip=\"' + outline.dataset.strip + '\"]').getBoundingClientRect();
        const box = outline.getBoundingClientRect();

        return [outline.dataset.strip, outline.querySelector('.tab').textContent.trim(), Math.abs(box.top - strip.top) < 1 && Math.abs(box.left - strip.left) < 1 && Math.abs(box.width - strip.width) < 1 && Math.abs(box.height - strip.height) < 1];
    })"))->toBe([
        ['hero', 'Hero4 velden', true],
        ['cards', 'Kaarten3 velden', true],
    ]);

    $shell->assertSeeIn('[data-slot="choose"]', __('made-cms-package::cms.edit.choose'));

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="cards"]'));

    $shell->assertSeeIn('[data-slot="name"]', 'Kaarten')
        ->assertSeeIn('[data-slot="position"]', 'Strook 2 van 2');

    expect($shell->script('() => '.outlines().".filter((outline) => outline.classList.contains('actief')).map((outline) => outline.dataset.strip)"))->toBe(['cards']);

    $shell->script("() => { const frame = document.querySelector('.bewerkframe'); frame.contentWindow.voorHerladen = true; frame.contentWindow.location.reload(); }");

    expect($shell->script(waitUntil("! document.querySelector('.bewerkframe').contentWindow.voorHerladen && ".outlines().'.length === 2 && '.outlines().".find((outline) => outline.classList.contains('actief'))?.dataset.strip === 'cards'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="name"]', 'Kaarten')
        ->assertSeeIn('[data-slot="position"]', 'Strook 2 van 2')
        ->assertNoJavaScriptErrors();
});

it('keeps the frame on the page when the editor clicks a link on the site', function (): void {
    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit');

    expect($shell->script(waitUntil(outlines().'.length === 2')))->toBeTrue();

    $shell->script("() => { const site = document.querySelector('.bewerkframe').contentDocument; const link = site.createElement('a'); link.href = '/elders'; link.textContent = 'Elders'; site.body.prepend(link); link.click(); }");

    expect($shell->script(waitUntil("document.querySelector('.bewerkframe').contentWindow.location.pathname === '/elders'", 1000)))->toBeFalse();

    $shell->assertNoJavaScriptErrors();
});

it('warns the developer about a strip whose view has no data-cms-strip on its root', function (): void {
    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit');

    expect($shell->script(waitUntil(outlines().'.length === 2')))->toBeTrue();

    $shell->script("() => { window.waarschuwingen = []; console.warn = (message) => window.waarschuwingen.push(String(message)); document.querySelector('.bewerkframe').contentDocument.querySelector('[data-cms-strip=\"cards\"]').removeAttribute('data-cms-strip'); }");

    expect($shell->script(waitUntil(outlines().".length === 1 && window.waarschuwingen.some((message) => message.includes('cards') && message.includes('data-cms-strip'))")))->toBeTrue();

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="hero"]'));

    $shell->assertSeeIn('[data-slot="position"]', 'Strook 1 van 1')
        ->assertNoJavaScriptErrors();
});

it('outlines a strip that the site renders after the page has loaded', function (): void {
    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit');

    expect($shell->script(waitUntil(outlines().'.length === 2')))->toBeTrue();

    // A site rendered in the browser (React without ssr) puts its strips in the page after it loaded.
    $shell->script("() => { const site = document.querySelector('.bewerkframe').contentDocument; const strip = site.querySelector('[data-cms-strip=\"hero\"]'); const copy = strip.cloneNode(true); strip.remove(); setTimeout(() => site.querySelector('main').append(copy), 200); }");

    expect($shell->script(waitUntil(outlines().'.length === 2 && '.outlines().".map((outline) => outline.dataset.strip).join() === 'cards,hero'")))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});
