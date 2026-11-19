<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\PagePlace;

it('stores the place of a page as a string value', function (): void {
    expect(PagePlace::ShareImage->value)->toBe('share_image');
});

it('labels the place in Dutch', function (): void {
    expect(PagePlace::ShareImage->label())->toBe('Deelafbeelding');
});

it('labels the place in English', function (): void {
    app()->setLocale('en');

    expect(PagePlace::ShareImage->label())->toBe('Share image');
});
