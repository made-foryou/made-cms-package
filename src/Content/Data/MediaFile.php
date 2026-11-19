<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Support\MediaLink;

/**
 * A file of the media library as a strip renders it and the side panel shows it: by its url, never by where it is on
 * the disk, and without the original of a photo. The thumbnail is for the CMS only; a strip renders the photo.
 *
 * @phpstan-type RenderedImage array{url: string, alt: string, width: int|null, height: int|null, focal: array{x: float, y: float}}
 * @phpstan-type RenderedFile array{url: string, name: string, extension: string, kind: string, mime: string, size: int, description: string|null}
 */
final readonly class MediaFile
{
    /**
     * @param  string  $url  the address a visitor opens the file at
     * @param  string|null  $thumb  the address of the file shown small in the CMS: the thumbnail of a photo, an image
     *                              without one itself (an svg, an animation), null for a document or a video
     * @param  string  $name  the slug of the file name without extension
     * @param  int  $size  in bytes
     * @param  int|null  $width  in pixels; null when the size is unknown (an svg)
     * @param  string|null  $alt  the alt text of the file, the same on every page
     * @param  float|null  $focalX  the point a crop keeps in view, from 0 (left) to 1 (right); null for none
     * @param  string|null  $folder  the name of the folder the file is in; null for none
     * @param  int|null  $folderId  the id of that folder; null for none
     * @param  bool  $croppable  whether the file is a photo with an original to cut it from again (CropImage)
     * @param  string|null  $key  the folder of the file on the disk (media/{ulid}), the key of its lasting link
     */
    public function __construct(
        public int $id,
        public MediaKind $kind,
        public string $url,
        public ?string $thumb,
        public string $name,
        public string $mime,
        public int $size,
        public ?int $width,
        public ?int $height,
        public ?string $alt,
        public ?float $focalX,
        public ?float $focalY,
        public ?string $folder,
        public ?int $folderId = null,
        public bool $croppable = false,
        public ?string $key = null,
    ) {}

    /**
     * The image as the site renders it. The alt text is always a text, empty when the file has none, so an <img> never
     * lacks one; without a focal point it is the centre, the place object-position starts from.
     *
     * @return RenderedImage
     */
    public function image(): array
    {
        return [
            'url' => $this->url,
            'alt' => $this->alt ?? '',
            'width' => $this->width,
            'height' => $this->height,
            'focal' => ['x' => $this->focalX ?? 0.5, 'y' => $this->focalY ?? 0.5],
        ];
    }

    /**
     * The file as the site renders a link to download or play it: its lasting link (so a link a visitor shared keeps
     * working after a new version or a new name), its name, the extension and type of the file a visitor gets, its
     * size in bytes and its description (the alt text of the file), null when it has none. Null for a file without a
     * folder of its own, which has no lasting link.
     *
     * @return RenderedFile|null
     */
    public function download(): ?array
    {
        if ($this->key === null) {
            return null;
        }

        return [
            'url' => MediaLink::url($this->key, $this->name),
            'name' => $this->name,
            'extension' => $this->extension(),
            'kind' => $this->kind->value,
            'mime' => $this->mime,
            'size' => $this->size,
            'description' => $this->alt === null || $this->alt === '' ? null : $this->alt,
        ];
    }

    /**
     * The extension of the file a visitor opens, in small letters: the type of what the site serves ("webp" for a photo
     * that was scaled down).
     */
    public function extension(): string
    {
        return strtolower(pathinfo((string) parse_url($this->url, PHP_URL_PATH), PATHINFO_EXTENSION));
    }
}
