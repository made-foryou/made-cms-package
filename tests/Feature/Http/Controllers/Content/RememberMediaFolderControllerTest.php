<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->page = Page::factory()->at('/teams')->create();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * The folder the upload field of the side panel puts a photo in, in the next edit shell.
 *
 * @return array{id: int|null, label: string}
 */
function folderOfNextShell(Page $page): array
{
    $html = (string) test()->get('made-cms/pages/'.$page->id.'/edit')->assertOk()->getContent();
    $template = HTMLDocument::createFromString($html, LIBXML_NOERROR)->querySelector('template[data-part="upload"]');
    $field = HTMLDocument::createFromString('<!doctype html><body>'.$template?->innerHTML, LIBXML_NOERROR)->querySelector('[data-upload]');

    /** @var array{folder: array{id: int|null, label: string}} $config */
    $config = json_decode((string) $field?->getAttribute('data-config'), true, flags: JSON_THROW_ON_ERROR);

    return $config['folder'];
}

it('remembers the folder an editor chose, for the uploads of the next edit shell', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);

    $this->putJson('made-cms/media/chosen-folder', ['folder' => $teams->id])
        ->assertNoContent()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(folderOfNextShell($this->page))->toBe(['id' => $teams->id, 'label' => 'de map Teams']);
});

it('forgets the folder when the editor chooses all files', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $this->putJson('made-cms/media/chosen-folder', ['folder' => $teams->id]);

    $this->putJson('made-cms/media/chosen-folder', ['folder' => null])->assertNoContent();

    expect(folderOfNextShell($this->page))->toBe(['id' => null, 'label' => 'Alle bestanden']);
});

it('falls back to all files once the folder is gone', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $this->putJson('made-cms/media/chosen-folder', ['folder' => $teams->id]);

    $teams->delete();

    expect(folderOfNextShell($this->page))->toBe(['id' => null, 'label' => 'Alle bestanden']);
});

it('refuses what is no folder', function (array $body): void {
    $this->putJson('made-cms/media/chosen-folder', $body)->assertUnprocessable()->assertJsonValidationErrors('folder');
})->with(['a word' => [['folder' => 'teams']], 'zero' => [['folder' => 0]], 'nothing' => [[]]]);

it('opens for every editor who opens the pages or the media library', function (Role $role): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->putJson('made-cms/media/chosen-folder', ['folder' => null])
        ->assertNoContent();
})->with([Role::Owner, Role::SeoSpecialist, Role::ContentManager, Role::Admin]);

it('forbids it to an editor who opens neither', function (): void {
    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->putJson('made-cms/media/chosen-folder', ['folder' => null])
        ->assertForbidden();
});

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->putJson('made-cms/media/chosen-folder', ['folder' => null])->assertUnauthorized();
});
