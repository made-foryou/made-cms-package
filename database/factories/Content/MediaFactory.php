<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

/**
 * A row of the media library. It writes no file to the disk; a test that needs one uploads it with UploadMedia.
 *
 * @extends Factory<Media>
 */
final class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * A photo that was scaled down to WebP, with its original and its thumbnail next to it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $folder = 'media/'.Str::lower((string) Str::ulid());

        return [
            'folder_id' => null,
            'kind' => MediaKind::Image,
            'name' => fake()->unique()->slug(3),
            'path' => fn (array $attributes): string => "{$folder}/{$attributes['name']}.webp",
            'original_path' => "{$folder}/original.jpg",
            'thumb_path' => "{$folder}/thumb.webp",
            'mime' => 'image/webp',
            'size' => fake()->numberBetween(80_000, 600_000),
            'width' => 1920,
            'height' => 1280,
            'alt' => null,
            'focal_x' => null,
            'focal_y' => null,
            'uploaded_by' => null,
        ];
    }

    /**
     * A pdf, kept as it was uploaded.
     */
    public function document(): self
    {
        return $this->kept(MediaKind::Document, 'application/pdf', 'pdf');
    }

    /**
     * An mp4, kept as it was uploaded.
     */
    public function video(): self
    {
        return $this->kept(MediaKind::Video, 'video/mp4', 'mp4');
    }

    /**
     * A photo that was scaled down before the library made thumbnails.
     */
    public function withoutThumbnail(): self
    {
        return $this->state(fn (): array => ['thumb_path' => null]);
    }

    public function in(MediaFolder $folder): self
    {
        return $this->state(fn (): array => ['folder_id' => $folder->id]);
    }

    public function withAlt(string $alt): self
    {
        return $this->state(fn (): array => ['alt' => $alt]);
    }

    private function kept(MediaKind $kind, string $mime, string $extension): self
    {
        return $this->state(fn (): array => [
            'kind' => $kind,
            'path' => fn (array $attributes): string => 'media/'.Str::lower((string) Str::ulid())."/{$attributes['name']}.{$extension}",
            'original_path' => null,
            'thumb_path' => null,
            'mime' => $mime,
            'width' => null,
            'height' => null,
        ]);
    }
}
