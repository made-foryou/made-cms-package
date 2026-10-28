<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Support\PagePath;

/**
 * Creates a page with its strips from its page file, unless the site already has a page at that path:
 * content edited in the CMS is never overwritten. Every strip is checked before anything is written.
 */
final class ImportPage
{
    use AsAction;

    /**
     * @return bool whether the page was created
     *
     * @throws InvalidStripType
     * @throws InvalidStripContent
     */
    public function handle(PageImport $import): bool
    {
        $path = PagePath::normalise($import->path);

        if (Page::query()->forPath($path)->exists()) {
            return false;
        }

        $types = ListStripTypes::run();
        $strips = [];

        foreach ($import->strips as $position => $strip) {
            $type = $types[$strip->type] ?? throw InvalidStripContent::unknownType($strip->type);

            $strips[] = [
                'type' => $strip->type,
                'position' => $position,
                'content' => ValidateStripContent::run($type, $strip->content),
            ];
        }

        $page = DB::transaction(function () use ($import, $path, $strips): Page {
            $page = Page::query()->create([
                'title' => $import->title,
                'path' => $path,
                'status' => PageStatus::Published,
                'published_at' => now(),
            ]);

            $page->strips()->createMany($strips);

            return $page;
        });

        PagePublished::dispatch($page);

        return true;
    }
}
