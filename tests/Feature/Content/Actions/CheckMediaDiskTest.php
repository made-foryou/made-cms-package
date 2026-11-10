<?php

declare(strict_types=1);

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\CheckMediaDisk;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;

it('gives the public disk by default', function (): void {
    Storage::fake('public');

    expect(CheckMediaDisk::run())->toBe(Storage::disk('public'))
        ->and(CheckMediaDisk::run())->toBeInstanceOf(Cloud::class);
});

it('gives the configured disk', function (): void {
    config([
        'filesystems.disks.media' => ['driver' => 'local', 'root' => storage_path('app/media'), 'url' => 'https://cdn.example.com/media'],
        'made-cms-package.media.disk' => 'media',
    ]);

    expect(CheckMediaDisk::run()->url('media/a/foto.webp'))->toBe('https://cdn.example.com/media/media/a/foto.webp');
});

it('refuses a disk the site does not have', function (): void {
    config(['made-cms-package.media.disk' => 'nope']);

    expect(fn (): Cloud => CheckMediaDisk::run())
        ->toThrow(MediaDiskNotUsable::class, 'The media disk [nope] is not in filesystems.disks.');
});

it('refuses a local disk without a url, whose files a visitor cannot open', function (): void {
    config([
        'filesystems.disks.local' => ['driver' => 'local', 'root' => storage_path('app/private')],
        'made-cms-package.media.disk' => 'local',
    ]);

    expect(fn (): Cloud => CheckMediaDisk::run())->toThrow(MediaDiskNotUsable::class, 'The media disk [local] gives no url');
});
