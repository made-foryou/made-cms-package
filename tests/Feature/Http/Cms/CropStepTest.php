<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Made\CmsPackage\Http\Cms\CropStep;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;

it('tells the script where it reads, previews and stores a file, by its id', function (): void {
    $step = CropStep::make(moves: true);

    expect($step['config']['urls'])->toBe([
        'details' => '/made-cms/media/:media/crop',
        'original' => '/made-cms/media/:media/original',
        'crop' => '/made-cms/media/:media/crop',
        'name' => '/made-cms/media/:media/name',
        'alt' => '/made-cms/media/:media/alt',
        'move' => '/made-cms/media/:media/move',
        'folders' => '/made-cms/media/folders',
    ])
        ->and($step['config'])->toHaveKey('csrf_token')
        ->and($step['config']['moves'])->toBeTrue()
        ->and($step['config']['max_width'])->toBe(1920)
        ->and($step['config']['format'])->toBe('WebP')
        ->and($step['script'])->toBe(LauncherAssetController::url('crop.js'))
        ->and($step['config']['texts'])->each->toBeString()
        ->and($step['config']['texts'])->toMatchArray([
            'save' => 'Opslaan',
            'save_next' => 'Opslaan en volgende',
            'area' => 'Uitsnede :ratio · :width × :height',
            'all_files' => 'Alle bestanden',
            'kb' => ':size kB',
        ]);
});

it('leaves moving a file out for an editor who does not open the media library', function (): void {
    $step = CropStep::make(moves: false);

    expect($step['config']['urls']['move'])->toBeNull()
        ->and($step['config']['urls']['folders'])->toBeNull()
        ->and($step['config']['moves'])->toBeFalse();
});

it('follows the configured width and format', function (): void {
    config(['made-cms-package.media.image.max_width' => 2400, 'made-cms-package.media.image.format' => 'jpg']);

    $step = CropStep::make(moves: false);

    expect($step['config']['max_width'])->toBe(2400)
        ->and($step['config']['format'])->toBe('JPG')
        ->and($step['t']('scale_hint'))->toStartWith('Maximaal 2400 px breed');
});

it('renders the step as a modal dialog with the stage, the ratios, the tools, the fields and a foot', function (bool $moves): void {
    $html = view('made-cms-package-launcher::cms.partials.crop', ['crop' => CropStep::make($moves)])->render();
    $document = HTMLDocument::createFromString('<!doctype html><body>'.$html, LIBXML_NOERROR);
    $dialog = $document->querySelector('dialog[data-media-crop]');
    $label = static fn (string $selector): ?string => $document->querySelector($selector)?->getAttribute('aria-label');

    expect($dialog?->getAttribute('aria-modal'))->toBe('true')
        ->and($document->getElementById((string) $dialog?->getAttribute('aria-labelledby'))?->textContent)->toBe('Maak de foto klaar voor de site')
        ->and(json_decode((string) $dialog?->getAttribute('data-config'), true)['moves'] ?? null)->toBe($moves)
        ->and(array_map(static fn ($button): string => trim($button->textContent), iterator_to_array($document->querySelectorAll('[data-ratio]'))))
        ->toBe(['Vrij', '16:9', '4:3', '3:2', '1:1', 'Origineel'])
        ->and($document->querySelectorAll('[data-ratio][aria-pressed="false"]')->length)->toBe(6)
        ->and($label('[data-slot="crop-left"]'))->toBe('Linksom draaien')
        ->and($label('[data-slot="crop-right"]'))->toBe('Rechtsom draaien')
        ->and($label('[data-slot="crop-flip"]'))->toBe('Spiegelen')
        ->and($label('[data-slot="crop-close"]'))->toBe('Sluiten')
        ->and($label('[data-slot="crop-focal"]'))->toBe('Brandpunt')
        ->and($label('[data-slot="crop-frame"]'))->toBe('Uitsnede')
        ->and(array_map(static fn ($corner): ?string => $corner->getAttribute('aria-label'), iterator_to_array($document->querySelectorAll('[data-corner]'))))
        ->toBe(['Hoek linksboven', 'Hoek rechtsboven', 'Hoek linksonder', 'Hoek rechtsonder'])
        ->and($document->querySelector('[data-slot="crop-scale-down"]')?->getAttribute('role'))->toBe('switch')
        ->and($document->querySelector('label[for="made-cms-bijsnijden-naam"]')?->textContent)->toBe('Bestandsnaam')
        ->and($document->querySelector('label[for="made-cms-bijsnijden-alt"]')?->textContent)->toBe('Alt-tekst')
        ->and($document->querySelector('[data-slot="crop-folder"]') !== null)->toBe($moves)
        ->and(trim((string) $document->querySelector('[data-slot="crop-keep"]')?->textContent))->toBe('Zo laten, zonder snijden')
        ->and($document->querySelector('[data-slot="crop-announce"]')?->getAttribute('aria-live'))->toBe('polite');
})->with(['with moving' => [true], 'without moving' => [false]]);
