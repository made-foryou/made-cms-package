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
        return $this->where('kind', MediaKind::Image);
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
     * The files a page uses: in its draft or its published version, also on a deleted page that can still be
     * restored. Such a file cannot be deleted.
     */
    public function inUse(): self
    {
        return $this->whereHas('usages');
    }

    /**
     * Loads the places of every file with their page, also a deleted page, for ListMediaPlaces.
     */
    public function withUsage(): self
    {
        return $this->with('usages.page');
    }

    /**
     * The files whose name or alt text holds the term, whatever the capitals; every file for a blank term.
     * Wildcards in the term are taken literally.
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
                ->orWhereRaw("lower(alt) like ? escape '!'", [$pattern]);
        });
    }
}
