<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Direction;
use Intervention\Image\Format;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Events\MediaCropped;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;
use Throwable;

final class CropImage
{
    use AsAction;

    /** The largest side WebP holds, the limit of a photo that is not scaled down. */
    private const int LARGEST = 16383;

    /**
     * Cuts a photo from its original again: the area as the editor saw it (CropArea), turned and mirrored, scaled
     * down to media.image.max_width unless the editor switched that off, and written as media.image.format at
     * media.image.quality without the metadata of the camera, with the focal point the site keeps in view. The
     * original stays as it was, so the next crop starts from it again.
     *
     * The photo gets a new address in a folder of its own next to the original ({home}/{8 random}/{name}), and a new
     * thumbnail (MakeThumbnail) from it, so no cache keeps the former photo; the former files leave the disk once the
     * row is committed. When only the focal point changed, no file is written. The photo changes on every page that
     * shows it, the published version too.
     *
     * @throws ValidationException keyed by "crop" for an area outside the photo or an original that cannot be read
     * @throws MediaNotCroppable for a file without an original
     * @throws MediaDiskNotUsable
     * @throws ImageDriverNotSupported
     * @throws MediaNotStored
     */
    public function handle(Media $media, CropMedia $crop): Media
    {
        if ($media->kind !== MediaKind::Image || $media->original_path === null) {
            throw MediaNotCroppable::id($media->id);
        }

        $disk = CheckMediaDisk::run();
        $focal = ['focal_x' => self::focal($crop->focalX), 'focal_y' => self::focal($crop->focalY)];

        if ($this->keepsPhoto($media, $crop)) {
            return $this->store($media, $focal);
        }

        $image = DecodeOriginal::run($media);
        $width = $image->width();
        $height = $image->height();

        if (! $crop->area->fits($width, $height)) {
            throw ValidationException::withMessages(['crop' => (string) __('made-cms-package::media.errors.crop_outside')]);
        }

        $cut = $crop->area->onOriginal($width, $height);
        $image = $image->crop($cut['width'], $cut['height'], $cut['x'], $cut['y']);

        // Scaled down before it turns: a quarter turn only moves pixels, so the photo is the same, and turning a photo
        // of 1920 px instead of the whole cut spares GD a copy of a hundred MB. Turned a quarter, its height becomes
        // the width of the photo.
        $largest = $crop->scaleDown ? config()->integer('made-cms-package.media.image.max_width') : self::LARGEST;
        $image = $crop->area->rotation === 90 || $crop->area->rotation === 270
            ? $image->scaleDown(min($image->width(), self::LARGEST), $largest)
            : $image->scaleDown($largest, min($image->height(), self::LARGEST));

        if ($crop->area->rotation !== 0) {
            $image = $image->rotate($crop->area->rotation);
        }

        if ($crop->area->flip) {
            $image = $image->flip(Direction::HORIZONTAL);
        }

        $format = Format::create(config()->string('made-cms-package.media.image.format'));
        $photo = $image->encodeUsingFormat($format, quality: config()->integer('made-cms-package.media.image.quality'))->toString();
        $thumbnail = MakeThumbnail::run($photo);

        $home = dirname($media->original_path);
        $path = $this->write($disk, "{$home}/".Str::lower(Str::random(8))."/{$media->name}.{$format->fileExtension()->value}", $photo);

        try {
            $thumb = $this->write($disk, "{$home}/thumb-".Str::random(40).".{$thumbnail->extension}", (string) $thumbnail->contents);
        } catch (MediaNotStored $exception) {
            $disk->deleteDirectory(dirname($path));

            throw $exception;
        }

        try {
            return $this->store($media, [
                ...$focal,
                'path' => $path,
                'thumb_path' => $thumb,
                'mime' => $format->mediaType()->value,
                'size' => strlen($photo),
                'width' => $image->width(),
                'height' => $image->height(),
                'original_width' => $width,
                'original_height' => $height,
                'crop' => $crop->scaleDown && $crop->area->isOriginal($width, $height)
                    ? null
                    : [...$crop->area->toArray(), 'scale_down' => $crop->scaleDown],
            ], $disk);
        } catch (Throwable $exception) {
            $disk->deleteDirectory(dirname($path));
            $disk->delete($thumb);

            throw $exception;
        }
    }

    /**
     * Whether the editor kept the area and the scaling as stored, so only the focal point changes.
     */
    private function keepsPhoto(Media $media, CropMedia $crop): bool
    {
        if ($media->original_width === null || $media->original_height === null) {
            return false;
        }

        $stored = CropArea::fromArray($media->crop) ?? CropArea::whole($media->original_width, $media->original_height);
        $scaleDown = ($media->crop['scale_down'] ?? true) !== false;

        return $crop->area == $stored && $crop->scaleDown === $scaleDown;
    }

    /**
     * Stores the columns on the row as it is now, locked, and takes the photo and the thumbnail it replaced off the
     * disk once the change is committed.
     *
     * @param  array<string, mixed>  $columns
     */
    private function store(Media $media, array $columns, ?Cloud $disk = null): Media
    {
        return DB::transaction(static function () use ($media, $columns, $disk): Media {
            $stored = Media::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $former = [$stored->path, $stored->thumb_path];

            $stored->update($columns);
            MediaCropped::dispatch($stored);

            if ($disk !== null) {
                DB::afterCommit(static function () use ($disk, $stored, $former): void {
                    self::forget($disk, $stored, ...$former);
                });
            }

            return $stored;
        });
    }

    /**
     * Takes a former photo and its thumbnail off the disk: the folder of its own of a photo that was cropped before,
     * only the file of a photo as it was uploaded, next to its original.
     */
    private static function forget(Cloud $disk, Media $media, string $path, ?string $thumb): void
    {
        if ($path !== $media->path) {
            dirname($path) === dirname((string) $media->original_path)
                ? $disk->delete($path)
                : $disk->deleteDirectory(dirname($path));
        }

        if ($thumb !== null && $thumb !== $media->thumb_path) {
            $disk->delete($thumb);
        }
    }

    /**
     * @throws MediaNotStored
     */
    private function write(Cloud $disk, string $path, string $contents): string
    {
        if (! $disk->put($path, $contents)) {
            throw MediaNotStored::at($path);
        }

        return $path;
    }

    /**
     * A focal point on the photo, from 0 to 1, to the four decimals the column holds.
     */
    private static function focal(float $value): float
    {
        return round(max(0.0, min(1.0, $value)), 4);
    }
}
