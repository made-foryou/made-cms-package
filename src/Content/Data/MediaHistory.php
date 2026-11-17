<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Models\Media;

/**
 * Who uploaded a file of the media library and when, which version the site shows, who placed it and when, and the
 * version that waits to be put back: the line under the actions of the library ("Geüpload door … · versie 2").
 * Editors are ids; the names belong to Access.
 */
final readonly class MediaHistory
{
    /**
     * @param  int|null  $uploadedBy  the editor who uploaded the file; null for none, such as an import
     * @param  int  $version  the number of the version the site shows
     * @param  int|null  $replacedBy  the editor who placed that version by replacing the file; null for the upload
     * @param  CarbonImmutable|null  $replacedAt  when that version was placed; null for the upload
     * @param  MediaVersion|null  $previous  the version that waits to be put back; null for none
     */
    public function __construct(
        public ?int $uploadedBy,
        public ?CarbonImmutable $uploadedAt,
        public int $version,
        public ?int $replacedBy,
        public ?CarbonImmutable $replacedAt,
        public ?MediaVersion $previous,
    ) {}

    public static function of(Media $media): self
    {
        return new self(
            uploadedBy: $media->uploaded_by,
            uploadedAt: $media->created_at === null ? null : CarbonImmutable::instance($media->created_at),
            version: $media->version,
            replacedBy: $media->replaced_by,
            replacedAt: $media->replaced_at,
            previous: MediaVersion::waiting($media),
        );
    }

    /**
     * The editors the line names, each once.
     *
     * @return list<int>
     */
    public function editors(): array
    {
        return array_values(array_unique(array_filter(
            [$this->uploadedBy, $this->replacedBy, $this->previous?->placedBy],
            static fn (?int $editor): bool => $editor !== null,
        )));
    }
}
