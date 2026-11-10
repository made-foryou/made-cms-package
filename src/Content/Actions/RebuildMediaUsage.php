<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\RebuiltMediaUsage;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

final class RebuildMediaUsage
{
    use AsAction;

    /**
     * Builds cms_media_usages again from the strips of every page, deleted pages too: for a site that had content
     * before the table, after content was written without the Actions (a seeder, an import), or after the strip types
     * of the site changed. Every page goes through SyncMediaUsage in a transaction of its own with its row locked, so
     * an editor can keep working meanwhile. A strip whose type left the catalogue keeps the rows it has; they cannot
     * be read from its values.
     */
    public function handle(): RebuiltMediaUsage
    {
        $ids = Page::query()->withTrashed()->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(static function () use ($id): void {
                $page = Page::query()->withTrashed()->whereKey($id)->lockForUpdate()->first();

                if ($page !== null) {
                    SyncMediaUsage::run($page);
                }
            });
        }

        return new RebuiltMediaUsage(pages: $ids->count(), places: MediaUsage::query()->count());
    }
}
