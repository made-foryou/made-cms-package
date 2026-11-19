<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\MeasureShareImage;
use Made\CmsPackage\Content\Data\ShareImageFit;

it('takes a photo of 1200 by 630 as it is', function (): void {
    expect(MeasureShareImage::run(1200, 630))->toEqual(new ShareImageFit(tooSmall: false, cut: 0, axis: null));
});

it('says how much of another shape a shared link cuts away, and where', function (int $width, int $height, int $cut, string $axis): void {
    expect(MeasureShareImage::run($width, $height))->toEqual(new ShareImageFit(tooSmall: false, cut: $cut, axis: $axis));
})->with([
    'square' => [1200, 1200, 48, 'y'],
    'three by two' => [1600, 1067, 21, 'y'],
    'upright' => [1080, 1350, 58, 'y'],
    'sixteen by nine' => [1920, 1080, 7, 'y'],
    'a wide band' => [2400, 600, 52, 'x'],
]);

it('takes a shape that cuts away only a sliver as it is', function (int $width, int $height): void {
    expect(MeasureShareImage::run($width, $height)->cut)->toBe(0)
        ->and(MeasureShareImage::run($width, $height)->axis)->toBeNull();
})->with([
    'a little higher' => [1200, 640],
    'a little wider' => [1220, 630],
]);

it('says a photo is small below 600 by 315, where apps show it small or not at all', function (int $width, int $height, bool $small): void {
    expect(MeasureShareImage::run($width, $height)->tooSmall)->toBe($small);
})->with([
    'the smallest that fits' => [600, 315, false],
    'too narrow' => [599, 315, true],
    'too low' => [1200, 314, true],
    'the example of the design' => [480, 252, true],
]);

it('says nothing of an image whose size it does not know', function (?int $width, ?int $height): void {
    expect(MeasureShareImage::run($width, $height))->toEqual(new ShareImageFit(tooSmall: false, cut: 0, axis: null));
})->with([[null, null], [1200, null], [null, 630], [0, 630]]);

it('gives the measure as an array for the card', function (): void {
    expect(MeasureShareImage::run(1200, 1200)->toArray())->toBe(['too_small' => false, 'cut' => 48, 'axis' => 'y']);
});
