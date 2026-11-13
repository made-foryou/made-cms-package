<?php

declare(strict_types=1);

use Dom\Element;
use Dom\HTMLDocument;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Cms\MediaBrowser;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;

it('tells the script where it lists the files and the folders and remembers a folder', function (): void {
    $config = MediaBrowser::make()['config'];

    expect($config['urls'])->toBe([
        'files' => '/made-cms/media/files',
        'folders' => '/made-cms/media/folders',
        'chosen_folder' => '/made-cms/media/chosen-folder',
    ])
        ->and($config)->toHaveKey('csrf_token')
        ->and($config['kind'])->toBeNull()
        ->and($config['folder'])->toBeNull()
        ->and($config['texts'])->each->toBeString()
        ->and($config['texts'])->toHaveKeys(['loading', 'failed', 'expired']);
});

it('opens in the folder and shows the kind the host gives', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);

    $browser = MediaBrowser::make($teams, MediaKind::Image);

    expect($browser['config']['folder'])->toBe(['id' => $teams->id, 'name' => 'Teams'])
        ->and($browser['config']['kind'])->toBe('image')
        ->and($browser['kind'])->toBe('Afbeeldingen')
        ->and($browser['upload'])->toBeNull()
        ->and($browser['script'])->toBe(LauncherAssetController::url('media.js'));
});

it('gives the picker of an image only images and the upload field of one photo, into the same folder', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);

    $picker = MediaBrowser::picker($teams);

    expect($picker['browser']['config']['kind'])->toBe('image')
        ->and($picker['browser']['upload']['one_image'] ?? null)->toBeTrue()
        ->and($picker['browser']['upload']['config']['crop'] ?? null)->toBeTrue()
        ->and($picker['browser']['upload']['config']['folder'] ?? null)->toBe(['id' => $teams->id, 'label' => 'de map Teams'])
        ->and($picker['config']['texts'])->toMatchArray([
            'for' => 'voor :field in :place',
            'use' => 'Deze gebruiken',
            'choose_first' => 'Kies eerst een afbeelding',
        ]);
});

it('renders the picker as a modal dialog with the list, its folders, the upload field and a footer', function (): void {
    $html = view('made-cms-package-launcher::cms.partials.media-picker', ['picker' => MediaBrowser::picker()])->render();
    $document = HTMLDocument::createFromString('<!doctype html><body>'.$html, LIBXML_NOERROR);
    $dialog = $document->querySelector('dialog[data-media-picker]');

    expect($dialog?->getAttribute('aria-modal'))->toBe('true')
        ->and($document->getElementById((string) $dialog?->getAttribute('aria-labelledby'))?->textContent)->toBe('Kies een afbeelding')
        ->and($document->getElementById((string) $dialog?->getAttribute('aria-describedby')))->not->toBeNull()
        ->and($dialog?->querySelector('[data-media] [data-slot="media-folders"] button[data-folder=""]')?->textContent)->toContain('Alle bestanden')
        ->and($dialog?->querySelector('[data-media] [data-upload][data-one-image]'))->not->toBeNull()
        ->and($dialog?->querySelector('[data-slot="media-search"]')?->getAttribute('type'))->toBe('search')
        ->and($document->querySelector('label[for="'.$dialog?->querySelector('[data-slot="media-search"]')?->getAttribute('id').'"]')?->textContent)->toBe('Zoek in media')
        ->and($dialog?->querySelector('[data-slot="media-kind"]')?->textContent)->toBe('Afbeeldingen')
        ->and($dialog?->querySelector('button[data-slot="media-newest"]')?->getAttribute('aria-pressed'))->toBe('true')
        ->and($dialog?->querySelector('button[data-slot="media-without-alt"]')?->getAttribute('aria-pressed'))->toBe('false')
        ->and($dialog?->querySelector('[data-slot="media-empty"]')?->textContent)->toBe('Geen afbeeldingen gevonden. Documenten en video’s kies je hier niet.')
        ->and($dialog?->querySelector('template[data-media-folder]'))->not->toBeNull()
        ->and($dialog?->querySelector('template[data-media-tile]'))->not->toBeNull()
        ->and($dialog?->querySelector('[data-slot="media-picker-use"]')?->getAttribute('aria-disabled'))->toBe('true')
        ->and($dialog?->querySelector('[data-slot="media-picker-close"]')?->getAttribute('aria-label'))->toBe('Sluiten')
        ->and(array_map(
            static fn (Element $slot): string => (string) $slot->getAttribute('data-slot'),
            iterator_to_array($dialog?->querySelectorAll('[data-slot]:not([data-slot^="media-"]):not([data-slot^="upload-"])') ?? []),
        ))->toBe([]);
});
