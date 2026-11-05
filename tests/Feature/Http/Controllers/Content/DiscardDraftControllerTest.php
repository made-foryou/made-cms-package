<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('throws the draft away and keeps the published strips', function (): void {
    $published = [storedStrip('cards', ['kop' => 'Teams'], id: 'a')];
    $page = Page::factory()->published()->withStrips($published)->withDraft([storedStrip('cards', ['kop' => 'Onze teams'], id: 'a')])->create();

    $response = $this->deleteJson('made-cms/pages/'.$page->id.'/draft')->assertNoContent();

    $stored = $page->refresh();

    expect($stored->draft_strips)->toBeNull()
        ->and($stored->strips)->toBe($published)
        ->and($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('answers the same without a draft', function (): void {
    $page = Page::factory()->published()->create();

    $this->deleteJson('made-cms/pages/'.$page->id.'/draft')->assertNoContent();
});

it('answers 401 to a guest', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();
    auth('made-cms')->logout();

    $this->deleteJson('made-cms/pages/'.$page->id.'/draft')->assertUnauthorized();

    expect($page->refresh()->draft_strips)->not->toBeNull();
});
