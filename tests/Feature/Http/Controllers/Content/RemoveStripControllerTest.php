<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('removes a strip from the draft and answers with the strip as it was stored and the new list', function (): void {
    $removed = storedStrip('hero', ['kop' => 'TTV Merwestad'], visible: false, id: 'b');
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a'), $removed])->create();

    $response = $this->deleteJson('made-cms/pages/'.$page->id.'/strips/b')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('removed', $removed);

    expect(array_column($response->json('strips'), 'id'))->toBe(['a'])
        ->and($response->json('counts'))->toBe('1 strook')
        ->and(array_column($page->refresh()->draft_strips ?? [], 'id'))->toBe(['a']);
});

it('removes a strip whose type left the catalogue', function (): void {
    $page = Page::factory()->withDraft([storedStrip('oud', ['x' => 1], id: 'a')])->create();

    $this->deleteJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertOk()
        ->assertJsonPath('removed.type', 'oud')
        ->assertJsonPath('strips', []);
});

it('answers 404 for a strip that is not on the page', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->deleteJson('made-cms/pages/'.$page->id.'/strips/x')->assertNotFound();
});

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->deleteJson('made-cms/pages/'.$page->id.'/strips/a')->assertUnauthorized();
});

it('notes the editor who removes the strip', function (): void {
    $editor = Editor::factory()->create();
    $this->actingAs($editor, 'made-cms');
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a'), storedStrip('hero', id: 'b')])->create();

    $this->deleteJson('made-cms/pages/'.$page->id.'/strips/b')->assertOk();

    expect($page->refresh()->updated_by)->toBe($editor->id);
});
