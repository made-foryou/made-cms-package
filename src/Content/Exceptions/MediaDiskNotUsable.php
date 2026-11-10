<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * The disk of the media library cannot hold its files: a mistake in the configuration of the site, not of an editor.
 */
final class MediaDiskNotUsable extends RuntimeException
{
    public static function missing(string $disk): self
    {
        return new self("The media disk [{$disk}] is not in filesystems.disks. Set made-cms-package.media.disk to a disk of the site.");
    }

    public static function withoutUrl(string $disk): self
    {
        return new self("The media disk [{$disk}] gives no url, so a visitor cannot open its files. Use the public disk (after storage:link), a disk with a url, or a cloud disk.");
    }
}
