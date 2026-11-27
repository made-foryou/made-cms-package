<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    $this->live = [storedStrip('hero', ['kop' => 'Welkom bij de club', 'knoppen' => []], id: 'hero')];
    $this->draft = [storedStrip('hero', ['kop' => 'Samen tafeltennissen', 'knoppen' => []], id: 'hero')];
    $this->moment = now()->addDays(3)->setTime(9, 0)->toImmutable();

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript expression for whether the field of the moment shows.
 */
function momentShows(): string
{
    return "getComputedStyle(document.querySelector('.planning')).display !== 'none'";
}

/**
 * A JavaScript condition on the text of a slot of the bar of the edit shell.
 */
function scheduleBarSays(string $slot, string $text): string
{
    return "document.querySelector('[data-slot=\"{$slot}\"]')?.textContent.trim() === ".json_encode($text, JSON_THROW_ON_ERROR);
}

it('plans the publication in the basics and shows it in the bar of the edit shell', function (): void {
    $page = Page::factory()->published()->at('/')->withStrips($this->live)->withDraft($this->draft)->create(['title' => 'Home']);

    $screen = visit('/made-cms/pages/'.$page->id);

    $screen->assertSeeIn('#made-cms-scheduled-hint', 'Nu live. Zet dit aan om een datum te kiezen waarop wijzigingen live gaan.');

    expect($screen->script('() => '.momentShows()))->toBeFalse();

    $screen->click('#made-cms-scheduled');

    expect($screen->script('() => '.momentShows()))->toBeTrue();

    $screen->type('#made-cms-publish_at', $this->moment->format('Y-m-d\TH:i'))
        ->click('section[aria-labelledby="made-cms-basis"] .knoppen button[type="submit"]')
        ->assertSee('Home is opgeslagen.')
        ->assertSeeIn('#made-cms-scheduled-hint', 'om 09:00 live, met wat er dan in het concept staat.')
        ->assertSeeIn('.planning', 'Toch nu publiceren');

    expect(Page::query()->findOrFail($page->id)->publish_at?->equalTo($this->moment))->toBeTrue();

    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil("document.querySelector('[data-slot=\"bar-stop\"]')?.getAttribute('aria-haspopup') === 'dialog'")))->toBeTrue()
        ->and($shell->script("() => [document.querySelector('[data-slot=\"bar-publish\"]').hidden, document.querySelector('[data-slot=\"bar-scheduled\"]').hidden]"))->toBe([true, false])
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-scheduled-summary\"]').textContent"))->toStartWith('Gepland · ')
        ->toEndWith(' 09:00');

    $shell->click('[data-slot="bar-scheduled-summary"]')
        ->assertSeeIn('[data-slot="bar-scheduled-text"]', 'om 09:00 live, met wat je tot dan wijzigt.')
        ->click('[data-slot="bar-scheduled-publish"]');

    expect($shell->script(waitUntil(scheduleBarSays('bar-state', 'Gepubliceerd'))))->toBeTrue()
        ->and($shell->script(waitUntil("! document.querySelector('[data-slot=\"bar-publish\"]').hidden && document.querySelector('[data-slot=\"bar-scheduled\"]').hidden")))->toBeTrue();

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->status)->toBe(PageStatus::Published)
        ->and($stored->strips)->toBeStoredAs($this->draft)
        ->and($stored->draft_strips)->toBeNull()
        ->and($stored->publish_at)->toBeNull();

    $shell->assertNoJavaScriptErrors();
});

it('leaves the edit shell without asking when the draft is planned', function (): void {
    $page = Page::factory()->published()->at('/')->withStrips($this->live)->withDraft($this->draft)->scheduledAt($this->moment)->create(['title' => 'Home']);

    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil("document.querySelector('[data-slot=\"bar-stop\"]')?.getAttribute('aria-haspopup') === 'dialog'")))->toBeTrue();

    $shell->click('[data-slot="bar-stop"]')->assertPathIs('/');

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeStoredAs($this->draft);
});

it('goes to the basics of the page to change the moment', function (): void {
    $page = Page::factory()->published()->at('/')->withStrips($this->live)->withDraft($this->draft)->scheduledAt($this->moment)->create(['title' => 'Home']);

    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil("document.querySelector('[data-slot=\"bar-stop\"]')?.getAttribute('aria-haspopup') === 'dialog'")))->toBeTrue();

    $shell->click('[data-slot="bar-scheduled-summary"]')
        ->click('[data-slot="bar-scheduled-change"]')
        ->assertPathIs('/made-cms/pages/'.$page->id)
        ->assertSeeIn('#made-cms-scheduled-hint', 'om 09:00 live, met wat er dan in het concept staat.')
        ->assertNoJavaScriptErrors();
});

it('fits the planned moment in a bar of 1024 pixels', function (): void {
    $page = Page::factory()->published()->at('/')->withStrips($this->live)->withDraft($this->draft)->scheduledAt($this->moment)->create(['title' => 'Home']);

    $shell = visit('/made-cms/pages/'.$page->id.'/edit')->resize(1024, 800);

    expect($shell->script(waitUntil(scheduleBarSays('bar-state', '1 wijziging'))))->toBeTrue()
        ->and($shell->script("() => document.querySelector('.bewerkbalk').getBoundingClientRect().height"))->toBe(56)
        ->and($shell->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue()
        ->and($shell->script("() => { const button = document.querySelector('[data-slot=\"bar-scheduled-summary\"]'); return button.checkVisibility() && button.getBoundingClientRect().right <= window.innerWidth; }"))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-scheduled-summary\"]').textContent"))->toStartWith('Gepland · ');
});
