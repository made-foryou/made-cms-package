<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Enums\ShareImageRefusal;
use RuntimeException;

final class ShareImageRefused extends RuntimeException
{
    private function __construct(
        public readonly ShareImageRefusal $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function because(ShareImageRefusal $reason, int $mediaId): self
    {
        return new self($reason, "The file [{$mediaId}] cannot be the image of a shared link: {$reason->value}.");
    }
}
