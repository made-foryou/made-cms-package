<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Enums\ShareImageRefusal;
use Made\CmsPackage\Content\Events\PageShareImageChanged;
use Made\CmsPackage\Content\Exceptions\ShareImageRefused;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

final class SetPageShareImage
{
    use AsAction;

    /**
     * Gives a page the image a shared link to it shows, or none of its own (null: the default of the site). It holds
     * for the site at once, outside the draft, and the file gets its place in cms_media_usages in the same
     * transaction, so the media library keeps it.
     *
     * It reads the page again with its row locked and gives the files of the page their places before it reads the
     * chosen file: SyncMediaUsage reads every file of the page with one shared lock in the order of their ids, the
     * order DeleteMedia locks them in, so the two never wait on each other. A refused file rolls everything back.
     *
     * @throws ShareImageRefused when the file is gone, is no image or is an svg
     */
    public function handle(Page $page, ?int $mediaId): Page
    {
        DB::transaction(static function () use ($page, $mediaId): void {
            $stored = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            $page->setRawAttributes($stored->getAttributes(), sync: true);

            if ($page->share_image_id === $mediaId) {
                return;
            }

            $page->share_image_id = $mediaId;
            SyncMediaUsage::run($page);

            if ($mediaId !== null) {
                $file = Media::query()->lockedForUsage([$mediaId])->first();
                $refusal = ShareImageRefusal::of($file?->kind, $file?->mime);

                if ($refusal !== null) {
                    $page->setRawAttributes($stored->getAttributes(), sync: true);

                    throw ShareImageRefused::because($refusal, $mediaId);
                }
            }

            $page->save();
            PageShareImageChanged::dispatch($page);
        });

        return $page;
    }
}
