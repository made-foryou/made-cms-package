<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Why a file cannot be the image of a shared link: apps show a photo with a link, never a document, a video or an svg.
 */
enum ShareImageRefusal: string
{
    case Gone = 'gone';
    case NotAnImage = 'kind';
    case Drawing = 'svg';

    /**
     * The reason a file of this kind and type cannot be shared, or null when it can; a file that is gone has neither.
     */
    public static function of(?MediaKind $kind, ?string $mime): ?self
    {
        return match (true) {
            $kind === null => self::Gone,
            $kind !== MediaKind::Image => self::NotAnImage,
            strtolower((string) $mime) === 'image/svg+xml' => self::Drawing,
            default => null,
        };
    }
}
