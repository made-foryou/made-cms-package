<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * What a file in the media library is. Only images are scaled down, cropped and get an alt text that a visitor
 * needs; documents and videos are plain files with a download link.
 */
enum MediaKind: string
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';

    /**
     * The kind of a file by the type of its contents, never by the extension of its name.
     */
    public static function fromMime(string $mime): self
    {
        return match (strstr(strtolower($mime), '/', before_needle: true)) {
            'image' => self::Image,
            'video' => self::Video,
            default => self::Document,
        };
    }

    public function label(): string
    {
        return (string) __('made-cms-package::media.kinds.'.$this->value);
    }
}
