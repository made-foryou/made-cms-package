<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\DescribeUploadLimits;
use Made\CmsPackage\Content\Data\UploadLimits;
use Made\CmsPackage\Content\Enums\MediaKind;

it('describes what the library takes as the site configured it', function (): void {
    expect(DescribeUploadLimits::run())->toEqual(new UploadLimits(
        maxBytes: 25600 * 1024,
        max: '25 MB',
        extensions: [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
        ],
        types: 'jpg, png, webp, gif, svg, pdf of mp4',
        kinds: [MediaKind::Image, MediaKind::Document, MediaKind::Video],
    ));
});

it('names a maximum that is no whole number of MB in kB', function (): void {
    config(['made-cms-package.media.max_upload_kb' => 1500]);

    $limits = DescribeUploadLimits::run();

    expect($limits->maxBytes)->toBe(1500 * 1024)
        ->and($limits->max)->toBe('1500 kB');
});

it('follows the types the site left in its list', function (): void {
    config(['made-cms-package.media.allowed_mimes' => ['image/png', 'image/jpeg', 'application/pdf', 12]]);

    $limits = DescribeUploadLimits::run();

    expect($limits->mimes())->toBe(['image/png', 'image/jpeg', 'application/pdf'])
        ->and($limits->types)->toBe('png, jpg of pdf')
        ->and($limits->kinds)->toBe([MediaKind::Image, MediaKind::Document]);
});

it('names a single type without a list', function (): void {
    config(['made-cms-package.media.allowed_mimes' => ['image/png']]);

    expect(DescribeUploadLimits::run()->types)->toBe('png');
});

it('gives the extension that goes with a type the library takes', function (): void {
    $limits = DescribeUploadLimits::run();

    expect($limits->extension('image/jpeg'))->toBe('jpg')
        ->and($limits->extension('image/vnd.adobe.photoshop'))->toBeNull();
});

it('never takes a type without an extension, and names it as it is', function (): void {
    config(['made-cms-package.media.allowed_mimes' => ['image/png', 'made/up']]);

    $limits = DescribeUploadLimits::run();

    expect($limits->extension('made/up'))->toBeNull()
        ->and($limits->mimes())->toBe(['image/png'])
        ->and($limits->types)->toBe('png of made/up');
});
