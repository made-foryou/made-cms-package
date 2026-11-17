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
        return [
            'ulid' => Str::lower((string) Str::ulid()),
            'folder_id' => null,
            'kind' => MediaKind::Image,
            'name' => fake()->unique()->slug(3),
            'path' => fn (array $attributes): string => "media/{$attributes['ulid']}/{$attributes['name']}.webp",
            'original_path' => fn (array $attributes): string => "media/{$attributes['ulid']}/original.jpg",
            'thumb_path' => fn (array $attributes): string => "media/{$attributes['ulid']}/thumb.webp",
            'mime' => 'image/webp',
            'size' => fake()->numberBetween(80_000, 600_000),
            'width' => 1920,
            'height' => 1280,
            'original_width' => 1920,
            'original_height' => 1280,
            'alt' => null,
            'focal_x' => null,
            'focal_y' => null,
            'crop' => null,
            'version' => 1,
            'previous' => null,
            'uploaded_by' => null,
            'replaced_by' => null,
            'replaced_at' => null,
        ];
    }

    /**
     * A photo that was replaced once: version 2 in a folder of its own (media/{ulid}/{8 random}), the upload waiting
     * next to it at the root of the file to be put back.
     */
    public function replaced(): self
    {
        return $this->state(fn (): array => [
            'version' => 2,
            'path' => fn (array $attributes): string => "media/{$attributes['ulid']}/k3f9a2b1/{$attributes['name']}.webp",
            'original_path' => fn (array $attributes): string => "media/{$attributes['ulid']}/k3f9a2b1/original.jpg",
            'thumb_path' => fn (array $attributes): string => "media/{$attributes['ulid']}/k3f9a2b1/thumb.webp",
            'replaced_at' => now()->toImmutable(),
            'previous' => fn (array $attributes): array => [
                'version' => 1,
                'name' => "{$attributes['name']}-oud",
                'path' => "media/{$attributes['ulid']}/{$attributes['name']}-oud.webp",
                'original_path' => "media/{$attributes['ulid']}/original.jpg",
                'thumb_path' => "media/{$attributes['ulid']}/thumb.webp",
                'mime' => 'image/webp',
                'size' => 240_000,
                'width' => 1600,
                'height' => 1067,
                'original_width' => 1600,
                'original_height' => 1067,
                'focal_x' => null,
                'focal_y' => null,
                'crop' => null,
                'placed_by' => null,
                'placed_at' => null,
            ],
        ]);
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
            'path' => fn (array $attributes): string => "media/{$attributes['ulid']}/{$attributes['name']}.{$extension}",
            'original_path' => null,
            'thumb_path' => null,
            'mime' => $mime,
            'width' => null,
            'height' => null,
            'original_width' => null,
            'original_height' => null,
        ]);
    }
}
