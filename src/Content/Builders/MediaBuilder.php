<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

/**
 * @extends Builder<Media>
 */
final class MediaBuilder extends Builder
{
    public function images(): self
    {
        return $this->ofKind(MediaKind::Image);
    }

    public function ofKind(MediaKind $kind): self
    {
        return $this->where('kind', $kind);
    }

    /**
     * @param  list<MediaKind>  $kinds
     */
    public function ofKinds(array $kinds): self
    {
        return $this->whereIn('kind', array_map(static fn (MediaKind $kind): string => $kind->value, $kinds));
    }

    public function inFolder(MediaFolder $folder): self
    {
        return $this->where('folder_id', $folder->id);
    }

    /**
     * The images a visitor gets without a description: the amber dot of the library.
     */
    public function withoutAlt(): self
    {
        return $this->images()->whereNull('alt');
    }

    /**
     * The photos that were scaled down when they came in, with their original next to them: the photos that have a
     * thumbnail. An svg, an animation, a document and a video are shown as they came in.
     */
    public function scaled(): self
    {
        return $this->images()->whereNotNull('original_path');
    }

    /**
     * The photos that were scaled down but have no thumbnail yet.
     */
    public function withoutThumbnail(): self
    {
        return $this->scaled()->whereNull('thumb_path');
    }

    /**
     * The files a page uses: in its draft or its published version, also on a deleted page that can still be
     * restored. Such a file cannot be deleted.
     */
    public function inUse(): self
    {
        return $this->whereHas('usages');
    }

    /**
     * The file whose folder on the disk, media/{ulid}, is this one: the key of the link that always leads to it.
     */
    public function linkedBy(string $ulid): self
    {
        return $this->where('ulid', $ulid);
    }

    /**
     * These files, read to give them a place on a page (SyncMediaUsage): each with a shared lock, in the order of their
     * ids, so a file DeleteMedia holds is read only once it is deleted or kept, and a file read here is deleted only
     * once the place is committed. The order keeps two such transactions from waiting on each other.
     *
     * @param  list<int>  $ids
     */
    public function lockedForUsage(array $ids): self
    {
        return $this->whereKey($ids)->orderBy('id')->sharedLock();
    }

    /**
     * Loads the places of every file with their page, also a deleted page, for ListMediaPlaces.
     */
    public function withUsage(): self
    {
        return $this->with('usages.page');
    }

    /**
     * The files the library got last first. Files stored within the same second follow the order they were stored in.
     */
    public function newestFirst(): self
    {
        return $this->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * The files by their name, from a to z; files with the same name in the order they were stored in.
     */
    public function byName(): self
    {
        return $this->orderBy('name')->orderBy('id');
    }

    /**
     * The number of these files in each folder, by the id of the folder, in one query. A file without a folder counts
     * in no folder, so "Alle bestanden" is the count() of the same query.
     *
     * @return array<int, int>
     */
    public function countPerFolder(): array
    {
        $counts = [];
        $rows = $this->clone()->reorder()->whereNotNull('folder_id')->toBase()
            ->select('folder_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('folder_id')
            ->orderBy('folder_id')
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->folder_id] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * The number of these files of each kind, by the value of the kind, in one query; a kind without files counts 0.
     *
     * @return array<string, int>
     */
    public function countPerKind(): array
    {
        $counts = array_fill_keys(array_map(static fn (MediaKind $kind): string => $kind->value, MediaKind::cases()), 0);
        $rows = $this->clone()->reorder()->toBase()
            ->select('kind')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('kind')
            ->get();

        foreach ($rows as $row) {
            $counts[(string) $row->kind] = (int) $row->aggregate;
        }

        return $counts;
    }

    /**
     * The files whose name or alt text holds the term, or the title of a page they are used on (in its draft or its
     * published version, never a deleted page), whatever the capitals; every file for a blank term. Wildcards in the
     * term are taken literally.
     */
    public function search(string $term): self
    {
        $term = trim($term);

        if ($term === '') {
            return $this;
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';

        return $this->where(function (MediaBuilder $query) use ($pattern): void {
            $query->whereRaw("lower(name) like ? escape '!'", [$pattern])
                ->orWhereRaw("lower(alt) like ? escape '!'", [$pattern])
                ->orWhereHas('usages.page', function (Builder $page) use ($pattern): void {
                    $page->whereNull($page->qualifyColumn('deleted_at'))
                        ->whereRaw("lower(title) like ? escape '!'", [$pattern]);
                });
        });
    }
}
