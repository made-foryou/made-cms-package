<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('publishes the draft', function (): void {
    $draft = [storedStrip('cards', ['kop' => 'Onze teams'], id: 'a')];
    $page = Page::factory()->published()->withStrips([storedStrip('cards', ['kop' => 'Teams'], id: 'a')])->withDraft($draft)->create();

    $response = $this->postJson('made-cms/pages/'.$page->id.'/publish')->assertNoContent();

    $stored = $page->refresh();

    expect($stored->strips)->toBeStoredAs($draft)
        ->and($stored->draft_strips)->toBeNull()
        ->and($stored->status)->toBe(PageStatus::Published)
        ->and($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('puts a page that was never published live', function (): void {
    $page = Page::factory()->create();

    $this->postJson('made-cms/pages/'.$page->id.'/publish')->assertNoContent();

    expect($page->refresh()->status)->toBe(PageStatus::Published);
});

it('answers the errors of a strip that breaks its definition by strip and field', function (): void {
    $published = [storedStrip('cards', id: 'a')];
    $page = Page::factory()->published()->withStrips($published)
        ->withDraft([storedStrip('hero', ['kop' => null, 'knoppen' => []], id: 'broken')])
        ->create();

    $this->postJson('made-cms/pages/'.$page->id.'/publish')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['strips.broken.kop' => 'Vul Kop in.']);

    expect($page->refresh()->strips)->toBeStoredAs($published);
});

it('refuses a live page without a draft', function (): void {
    $page = Page::factory()->published()->create();

    $this->postJson('made-cms/pages/'.$page->id.'/publish')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page' => 'Er is niets te publiceren: deze pagina is al live zoals hij is.']);
});

it('answers 401 to a guest', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();
    auth('made-cms')->logout();

    $this->postJson('made-cms/pages/'.$page->id.'/publish')->assertUnauthorized();

    expect($page->refresh()->status)->toBe(PageStatus::Draft);
});
