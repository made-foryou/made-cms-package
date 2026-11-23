<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('adds a strip of a type at a place in the draft and answers with it and the new list', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a'), storedStrip('cards', id: 'b')])->create();

    $response = $this->postJson('made-cms/pages/'.$page->id.'/strips', ['type' => 'hero', 'position' => 1])
        ->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private');

    $id = $response->json('strip.id');

    expect($id)->toBeUuid()
        ->and(array_column($page->refresh()->draft_strips ?? [], 'id'))->toBe(['a', $id, 'b'])
        ->and($response->json('strips.1'))->toBe([
            'id' => $id,
            'type' => 'hero',
            'label' => 'Hero',
            'visible' => true,
            'known' => true,
            'fields' => '7 velden',
            'summary' => 'Welkom',
            'headings' => [],
        ])
        ->and($response->json('counts'))->toBe('3 stroken');
});

it('refuses a type that is not in the catalogue and a place outside the page', function (array $input, string $field): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->postJson('made-cms/pages/'.$page->id.'/strips', $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($page->refresh()->draft_strips)->toHaveCount(1);
})->with([
    'unknown type' => [['type' => 'oud', 'position' => 0], 'type'],
    'no type' => [['position' => 0], 'type'],
    'too far' => [['type' => 'hero', 'position' => 2], 'position'],
    'negative' => [['type' => 'hero', 'position' => -1], 'position'],
    'no position' => [['type' => 'hero'], 'position'],
]);

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->create();

    $this->postJson('made-cms/pages/'.$page->id.'/strips', ['type' => 'hero', 'position' => 0])->assertUnauthorized();
});

it('notes the editor who adds the strip', function (): void {
    $editor = Editor::factory()->create();
    $this->actingAs($editor, 'made-cms');
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a')])->create();

    $this->postJson('made-cms/pages/'.$page->id.'/strips', ['type' => 'hero', 'position' => 1])->assertCreated();

    expect($page->refresh()->updated_by)->toBe($editor->id);
});
