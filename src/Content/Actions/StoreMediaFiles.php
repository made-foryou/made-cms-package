<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\CheckedMedia;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Enums\MediaFileRole;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;

final class StoreMediaFiles
{
    use AsAction;

    /**
     * Writes the files of an upload into a folder of the media disk and gives the columns that describe them, for a
     * new file (UploadMedia) and for a new version of one (ReplaceMedia). A photo that was scaled down becomes
     * {name}.{format}, with the upload next to it under a name nobody guesses from the url of the photo and its
     * thumbnail (MakeThumbnail); anything else is {name}.{extension} exactly as it came in. Each file is written as
     * its role says (MediaFileRole). The caller takes the folder off the disk again when this or its row fails.
     *
     * @param  string  $file  the local path of the upload
     * @param  ScaledImage|null  $image  the photo scaled down (ScaleImage); null for a file kept as it came in
     * @return array{path: string, original_path: string|null, thumb_path: string|null, mime: string, size: int, width: int|null, height: int|null, original_width: int|null, original_height: int|null}
     *
     * @throws MediaNotStored
     */
    public function handle(Cloud $disk, string $directory, string $name, string $file, CheckedMedia $checked, ?ScaledImage $image): array
    {
        if ($image === null || $image->contents === null) {
            return [
                'path' => $this->put($disk, $directory, $file, "{$name}.{$checked->extension}", MediaFileRole::AsUploaded),
                'original_path' => null,
                'thumb_path' => null,
                'mime' => $checked->mime,
                'size' => $checked->size,
                'width' => $image?->width,
                'height' => $image?->height,
                'original_width' => null,
                'original_height' => null,
            ];
        }

        $thumbnail = MakeThumbnail::run($image->contents);
        $original = $this->put($disk, $directory, $file, 'original-'.Str::random(40).".{$checked->extension}", MediaFileRole::Original);
        $path = $this->write($disk, "{$directory}/{$name}.{$image->extension}", $image->contents, MediaFileRole::Photo);
        $thumb = $this->write($disk, "{$directory}/thumb-".Str::random(40).".{$thumbnail->extension}", (string) $thumbnail->contents, MediaFileRole::Thumbnail);

        return [
            'path' => $path,
            'original_path' => $original,
            'thumb_path' => $thumb,
            'mime' => $image->mime,
            'size' => strlen($image->contents),
            'width' => $image->width,
            'height' => $image->height,
            'original_width' => $image->sourceWidth,
            'original_height' => $image->sourceHeight,
        ];
    }

    /**
     * @throws MediaNotStored
     */
    private function write(Cloud $disk, string $path, string $contents, MediaFileRole $role): string
    {
        if (! $disk->put($path, $contents, $role->writeOptions())) {
            throw MediaNotStored::at($path);
        }

        return $path;
    }

    /**
     * @throws MediaNotStored
     */
    private function put(Cloud $disk, string $directory, string $file, string $name, MediaFileRole $role): string
    {
        $path = $disk->putFileAs($directory, $file, $name, $role->writeOptions());

        if (! is_string($path)) {
            throw MediaNotStored::at("{$directory}/{$name}");
        }

        return $path;
    }
}
