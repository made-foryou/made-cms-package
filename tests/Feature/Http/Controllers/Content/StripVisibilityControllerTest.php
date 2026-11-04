<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('hides a strip in the draft and shows it again', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a')])->create();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a/visibility', ['visible' => false])
        ->assertOk()
        ->assertJsonPath('strip.id', 'a')
        ->assertJsonPath('strip.visible', false);

    expect($page->refresh()->draft_strips[0]['visible'] ?? null)->toBeFalse()
        ->and($page->strips[0]['visible'] ?? null)->toBeTrue();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a/visibility', ['visible' => true])
        ->assertJsonPath('strip.visible', true);
});

it('wants to know whether the strip is visible', function (mixed $visible): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a/visibility', ['visible' => $visible])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('visible');
})->with(['missing' => null, 'not a boolean' => 'misschien']);

it('answers 404 for a strip that is not on the page', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/x/visibility', ['visible' => false])->assertNotFound();
});
