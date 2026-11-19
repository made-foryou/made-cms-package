<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\DescribeShareImage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\ShareImage;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Enums\ShareImageRefusal;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.com']);
    Storage::fake('public', ['url' => '/storage']);
});

/**
 * A page that holds a file as its share image, without a place in the usage: describing only reads the column.
 */
function sharing(Media $media): Page
{
    return Page::factory()->withShareImage($media)->create();
}

it('gives the photo the site shows, absolute from the url of the site, never its thumbnail or its original', function (): void {
    $media = UploadMedia::run(new UploadedMedia(photo(1600, 840), 'open dag.jpg'));
    $media->update(['alt' => '  Bezoekers op de   open dag ']);

    $description = DescribeShareImage::run(sharing($media));

    expect($description->own)->toEqual(new ShareImage(
        url: 'https://example.com/storage/'.$media->path,
        width: 1600,
        height: 840,
        alt: 'Bezoekers op de open dag',
    ))
        ->and($description->own?->url)->not->toContain((string) $media->thumb_path)
        ->and($description->own?->url)->not->toContain((string) $media->original_path)
        ->and($description->file?->id)->toBe($media->id)
        ->and($description->refusal)->toBeNull()
        ->and($description->shown())->toBe($description->own);
});

it('gives no alt text for a file without one', function (string $alt): void {
    $media = Media::factory()->create(['alt' => $alt]);

    expect(DescribeShareImage::run(sharing($media))->own?->alt)->toBeNull();
})->with(['empty' => [''], 'blank' => ['   ']]);

it('keeps the address of a disk that gives absolute addresses', function (): void {
    Storage::fake('public', ['url' => 'https://cdn.example.net/media-bucket']);
    $media = Media::factory()->create();

    expect(DescribeShareImage::run(sharing($media))->own?->url)->toBe('https://cdn.example.net/media-bucket/'.$media->path);
});

it('falls back to the default of the site for a file a shared link cannot show, and says why', function (Closure $file, ShareImageRefusal $refusal): void {
    config(['made-cms-package.meta.share_image' => '/images/deel.jpg']);
    $page = Page::factory()->create();
    $page->share_image_id = $file();

    $description = DescribeShareImage::run($page);

    expect($description->own)->toBeNull()
        ->and($description->refusal)->toBe($refusal)
        ->and($description->shown())->toEqual(new ShareImage('https://example.com/images/deel.jpg'));
})->with([
    'a file that is gone' => [fn (): int => missingId(), ShareImageRefusal::Gone],
    'a document' => [fn (): int => Media::factory()->document()->create()->id, ShareImageRefusal::NotAnImage],
    'a drawing' => [fn (): int => Media::factory()->create(['mime' => 'image/svg+xml'])->id, ShareImageRefusal::Drawing],
]);

it('falls back to the default of the site when the media disk gives no address a visitor can open', function (): void {
    config(['made-cms-package.meta.share_image' => '/images/deel.jpg', 'filesystems.disks.public.url' => null]);
    Storage::fake('public', ['url' => null]);
    $media = Media::factory()->create();

    $description = DescribeShareImage::run(sharing($media));

    expect($description->own)->toBeNull()
        ->and($description->shown()?->url)->toBe('https://example.com/images/deel.jpg');
});

it('gives the default of the site for a page without a share image of its own', function (): void {
    config(['made-cms-package.meta.share_image' => [
        'url' => 'https://cdn.example.net/deel.jpg',
        'width' => '1200',
        'height' => 630,
        'alt' => ' Het   clubhuis ',
    ]]);

    $description = DescribeShareImage::run(Page::factory()->create());

    expect($description->own)->toBeNull()
        ->and($description->file)->toBeNull()
        ->and($description->refusal)->toBeNull()
        ->and($description->fallback)->toEqual(new ShareImage('https://cdn.example.net/deel.jpg', 1200, 630, 'Het clubhuis'))
        ->and($description->shown())->toBe($description->fallback);
});

it('gives no image without a default of the site', function (mixed $config): void {
    config(['made-cms-package.meta.share_image' => $config]);

    $description = DescribeShareImage::run(Page::factory()->create());

    expect($description->fallback)->toBeNull()
        ->and($description->shown())->toBeNull();
})->with([
    'none' => [null],
    'an empty url' => [['url' => null, 'width' => 1200, 'height' => 630, 'alt' => null]],
    'an address it cannot share' => [['url' => 'javascript:alert(1)']],
    'a relative path' => ['images/deel.jpg'],
    'no text' => [42],
    'a list' => [['/images/deel.jpg']],
]);

it('leaves out a size or an alt text of the default it cannot read', function (): void {
    config(['made-cms-package.meta.share_image' => ['url' => '/images/deel.jpg', 'width' => 'breed', 'height' => -630, 'alt' => ['clubhuis']]]);

    expect(DescribeShareImage::run(Page::factory()->create())->fallback)
        ->toEqual(new ShareImage('https://example.com/images/deel.jpg', null, null, null));
});
