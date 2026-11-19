<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The image a shared link to a page shows (og:image): the photo the site shows, or the default of the site.
 *
 * @phpstan-type SharedImage array{url: string, width: int|null, height: int|null, alt: string|null}
 */
final readonly class ShareImage
{
    /**
     * @param  string  $url  absolute, so an app opens it from anywhere
     * @param  int|null  $width  in pixels; null when it is not known
     * @param  string|null  $alt  the alt text of the file on one line; null for none
     */
    public function __construct(
        public string $url,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $alt = null,
    ) {}

    /**
     * @return SharedImage
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
        ];
    }
}
