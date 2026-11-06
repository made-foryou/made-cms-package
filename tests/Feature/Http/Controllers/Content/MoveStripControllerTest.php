<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('moves a strip of the draft to another place and answers with the new list', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a'), storedStrip('hero', id: 'b'), storedStrip('cards', id: 'c')])->create();

    $response = $this->putJson('made-cms/pages/'.$page->id.'/strips/a/position', ['position' => 2])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(array_column($response->json('strips'), 'id'))->toBe(['b', 'c', 'a'])
        ->and(array_column($page->refresh()->draft_strips ?? [], 'id'))->toBe(['b', 'c', 'a'])
        ->and(array_column($page->strips, 'id'))->toBe(['a', 'b', 'c']);
});

it('refuses a place outside the page', function (mixed $position): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a'), storedStrip('cards', id: 'b')])->create();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a/position', ['position' => $position])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('position');
})->with(['too far' => 2, 'negative' => -1, 'missing' => null]);

it('answers 404 for a strip that is not on the page', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/x/position', ['position' => 0])->assertNotFound();
});

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a/position', ['position' => 0])->assertUnauthorized();
});
