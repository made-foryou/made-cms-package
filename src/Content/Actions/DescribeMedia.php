<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaDescription;
use Made\CmsPackage\Content\Data\MediaHistory;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;

final class DescribeMedia
{
    use AsAction;

    /**
     * A file as the media library describes it next to its list: the file with the url a visitor opens it at, the
     * places it is used (ListMediaPlaces), a deleted page too, and its history (MediaHistory).
     *
     * @throws MediaDiskNotUsable
     * @throws ModelNotFoundException<Media> when the file left the library in the meantime
     */
    public function handle(Media $media): MediaDescription
    {
        $file = FindMedia::run([$media->id])->find($media->id)
            ?? throw (new ModelNotFoundException)->setModel(Media::class, [$media->id]);

        return new MediaDescription($file, ListMediaPlaces::run($media), MediaHistory::of($media));
    }
}
