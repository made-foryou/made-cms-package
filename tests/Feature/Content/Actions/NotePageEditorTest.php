<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\NotePageEditor;
use Made\CmsPackage\Content\Models\Page;

it('notes the editor of a change the page is about to save', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->create(['title' => 'Over ons']);
    $page->title = 'Wie we zijn';

    NotePageEditor::run($page, $editor->id);

    expect($page->updated_by)->toBe($editor->id);
});

it('leaves who changed the page last when nothing is about to change, so a save stays no write', function (): void {
    $before = Editor::factory()->create();
    $page = Page::factory()->create(['updated_by' => $before->id]);

    NotePageEditor::run($page, Editor::factory()->create()->id);

    expect($page->updated_by)->toBe($before->id)
        ->and($page->isDirty())->toBeFalse();
});

it('forgets who changed the page last when a change comes without an editor, such as an import', function (): void {
    $page = Page::factory()->create(['title' => 'Over ons', 'updated_by' => Editor::factory()->create()->id]);
    $page->title = 'Wie we zijn';

    NotePageEditor::run($page, null);

    expect($page->updated_by)->toBeNull();
});
