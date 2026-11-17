<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\DeletedMedia;
use Made\CmsPackage\Content\Events\MediaDeleted;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Throwable;

final class DeleteMedia
{
    use AsAction;

    /**
     * Deletes the files of the media library that no page uses, and keeps the rest: a file with a place in either
     * version of a page, also in a hidden strip or on a deleted page until it is pruned, stays. A deleted file leaves
     * with every version of it; its folder on the disk (media/{ulid}) goes once the change is committed
     * (RemoveMediaFiles). There is no bin: it is gone for good.
     *
     * The files are locked first, in the order of their ids, and only then is their use read: SyncMediaUsage reads
     * the files it gives a place with a shared lock, so a place committed before this lock is seen, and a place on
     * its way waits and finds the file gone. The foreign key of cms_media_usages is the last guard: a place a
     * transaction of the host holds with an older snapshot fails the delete with a QueryException.
     *
     * @param  list<int>  $mediaIds
     *
     * @throws MediaDiskNotUsable
     */
    public function handle(array $mediaIds): DeletedMedia
    {
        CheckMediaDisk::run();
        $ids = array_values(array_unique($mediaIds));

        return DB::transaction(static function () use ($ids): DeletedMedia {
            $files = Media::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            $found = array_values(array_map(static fn (Media $media): int => $media->id, $files->all()));
            $used = array_flip(array_map(
                intval(...),
                MediaUsage::query()->ofMedia($found)->distinct()->pluck('media_id')->all(),
            ));
            $deleted = [];
            $kept = [];

            foreach ($files as $media) {
                if (isset($used[$media->id])) {
                    $kept[] = $media;

                    continue;
                }

                $media->delete();
                $deleted[] = $media;
                MediaDeleted::dispatch($media);

                $ulid = $media->ulid;
                DB::afterCommit(static function () use ($ulid): void {
                    try {
                        RemoveMediaFiles::run($ulid);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                });
            }

            return new DeletedMedia(
                deleted: $deleted,
                kept: $kept,
                missing: array_values(array_diff($ids, $found)),
            );
        });
    }
}
