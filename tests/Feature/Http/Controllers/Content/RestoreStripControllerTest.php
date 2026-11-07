<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('puts a removed strip back with its id, values and visibility', function (): void {
    $hero = storedStrip('hero', ['label' => null, 'kop' => 'TTV Merwestad', 'intro' => null, 'tekst' => null, 'actie' => null, 'knoppen' => null], visible: false, id: 'b');
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a'), $hero])->create();

    $removed = $this->deleteJson('made-cms/pages/'.$page->id.'/strips/b')->json('removed');

    $response = $this->postJson('made-cms/pages/'.$page->id.'/strips/b/restore', [...$removed, 'position' => 1])
        ->assertCreated()
        ->assertJsonPath('strip.id', 'b')
        ->assertJsonPath('counts', '2 stroken, 1 verborgen');

    expect(array_column($response->json('strips'), 'id'))->toBe(['a', 'b'])
        ->and($page->refresh()->draft_strips)->toBeNull();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')->assertJsonPath('draft.changes', 0);
});

it('refuses a strip that is still on the page, a place outside it and values its type refuses', function (array $input, string $field): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->postJson('made-cms/pages/'.$page->id.'/strips/'.($input['id'] ?? 'b').'/restore', [
        'type' => 'hero',
        'visible' => true,
        'values' => ['kop' => 'Welkom'],
        'position' => 0,
        ...$input,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'still on the page' => [['id' => 'a'], 'strip'],
    'too far' => [['position' => 2], 'position'],
    'unknown type' => [['type' => 'oud'], 'type'],
    'refused value' => [['values' => ['kop' => null]], 'kop'],
    'no values' => [['values' => null], 'values'],
    'no visibility' => [['visible' => null], 'visible'],
]);

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->withDraft([])->create();

    $this->postJson('made-cms/pages/'.$page->id.'/strips/b/restore', ['type' => 'hero', 'visible' => true, 'values' => [], 'position' => 0])
        ->assertUnauthorized();
});
