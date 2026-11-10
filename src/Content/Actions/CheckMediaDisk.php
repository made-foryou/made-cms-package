<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;

final class CheckMediaDisk
{
    use AsAction;

    /**
     * The disk the media library stores its files on, once it is sure a visitor can open them by url. It runs when
     * the library is first used and never while the app boots, so a wrong disk leaves the site and artisan working.
     * A local disk without a url would quietly answer "/storage/…", so its configuration is read here.
     *
     * @throws MediaDiskNotUsable
     */
    public function handle(): Cloud
    {
        $name = config()->string('made-cms-package.media.disk');
        $config = config("filesystems.disks.{$name}");

        if (! is_array($config)) {
            throw MediaDiskNotUsable::missing($name);
        }

        if (($config['driver'] ?? null) === 'local' && in_array($config['url'] ?? null, [null, ''], true)) {
            throw MediaDiskNotUsable::withoutUrl($name);
        }

        return Storage::disk($name);
    }
}
