<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\CheckedMedia;
use Made\CmsPackage\Content\Data\MediaName;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Events\MediaUploaded;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Throwable;

final class UploadMedia
{
    use AsAction;

    /**
     * Adds a file to the media library. Each file gets a folder of its own on the media disk, media/{ulid}, so a
     * name never clashes and renaming it later moves nothing. A photo is scaled down (ScaleImage) to
     * {name}.{format}, with the upload next to it under a name nobody guesses from the url of the photo, because it
     * still holds the full size and the metadata of the camera, and its thumbnail (MakeThumbnail) as
     * thumb-{random}.{format} for the tiles of the CMS. Anything else (an svg, an animation, a document, a video) is
     * stored as {name}.{extension} exactly as it came in. The file is checked before anything is stored, and a row
     * that cannot be stored takes its files off the disk again.
     *
     * @throws ValidationException keyed by "file" for a file the library does not take
     * @throws MediaFolderNotFound
     * @throws MediaDiskNotUsable
     * @throws ImageDriverNotSupported
     * @throws MediaNotStored
     */
    public function handle(UploadedMedia $upload): Media
    {
        $disk = CheckMediaDisk::run();
        $checked = CheckUploadedMedia::run($upload->path);
        $folder = $upload->folderId === null
            ? null
            : MediaFolder::query()->find($upload->folderId) ?? throw MediaFolderNotFound::id($upload->folderId);
        $image = $checked->kind === MediaKind::Image && $checked->mime !== 'image/svg+xml'
            ? ScaleImage::run($upload->path, $checked)
            : null;

        $name = MediaName::fromFilename($upload->filename, $checked->kind)->value;
        $directory = 'media/'.Str::lower((string) Str::ulid());

        try {
            $media = Media::query()->create([
                'folder_id' => $folder?->id,
                'kind' => $checked->kind,
                'name' => $name,
                ...$this->store($disk, $directory, $name, $upload->path, $checked, $image),
                'uploaded_by' => $upload->uploadedBy,
            ]);
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }

        MediaUploaded::dispatch($media);

        return $media;
    }

    /**
     * Writes the files and gives the columns that describe them.
     *
     * @return array{path: string, original_path: string|null, thumb_path: string|null, mime: string, size: int, width: int|null, height: int|null, original_width: int|null, original_height: int|null}
     *
     * @throws MediaNotStored
     */
    private function store(Cloud $disk, string $directory, string $name, string $file, CheckedMedia $checked, ?ScaledImage $image): array
    {
        if ($image === null || $image->contents === null) {
            return [
                'path' => $this->put($disk, $directory, $file, "{$name}.{$checked->extension}"),
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
        $original = $this->put($disk, $directory, $file, 'original-'.Str::random(40).".{$checked->extension}");
        $path = $this->write($disk, "{$directory}/{$name}.{$image->extension}", $image->contents);
        $thumb = $this->write($disk, "{$directory}/thumb-".Str::random(40).".{$thumbnail->extension}", (string) $thumbnail->contents);

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
    private function write(Cloud $disk, string $path, string $contents): string
    {
        if (! $disk->put($path, $contents)) {
            throw MediaNotStored::at($path);
        }

        return $path;
    }

    /**
     * @throws MediaNotStored
     */
    private function put(Cloud $disk, string $directory, string $file, string $name): string
    {
        $path = $disk->putFileAs($directory, $file, $name);

        if (! is_string($path)) {
            throw MediaNotStored::at("{$directory}/{$name}");
        }

        return $path;
    }
}
