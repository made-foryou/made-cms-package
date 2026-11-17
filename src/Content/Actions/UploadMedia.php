<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaName;
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
     * stored as {name}.{extension} exactly as it came in. Each file is written as its role says (MediaFileRole): the
     * original private, the rest public, whatever the disk says. The file is checked before anything is stored, and a
     * row that cannot be stored takes its files off the disk again.
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
        $ulid = Str::lower((string) Str::ulid());
        $directory = "media/{$ulid}";

        try {
            $columns = StoreMediaFiles::run($disk, $directory, $name, $upload->path, $checked, $image);
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }

        // The files leave again only when the row cannot be stored, from inside the transaction, never around it: a
        // callback after the commit that throws must not take the files of a stored row away.
        $media = DB::transaction(static function () use ($disk, $directory, $ulid, $folder, $checked, $name, $columns, $upload): Media {
            try {
                // The folder is read again with a shared lock, so a folder deleted while the file was scaled down is
                // refused like a folder that never was, and one cannot be deleted before the file is in it.
                if ($folder !== null) {
                    MediaFolder::query()->whereKey($folder->id)->sharedLock()->first() ?? throw MediaFolderNotFound::id($folder->id);
                }

                return Media::query()->create([
                    'ulid' => $ulid,
                    'folder_id' => $folder?->id,
                    'kind' => $checked->kind,
                    'name' => $name,
                    ...$columns,
                    'uploaded_by' => $upload->uploadedBy,
                ]);
            } catch (Throwable $exception) {
                $disk->deleteDirectory($directory);

                throw $exception;
            }
        });

        MediaUploaded::dispatch($media);

        return $media;
    }
}
