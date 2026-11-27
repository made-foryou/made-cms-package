<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

final class ListWorkingCopyMedia
{
    use AsAction;

    /**
     * The files these places of a page put on its working copy, each once, in the order the page shows them: by the
     * place of their strip in the working copy, then the place stored first. A place of another version, or of the
     * page itself (its share image), is left out.
     *
     * @param  iterable<MediaUsage>  $usages  places of this page, of any version
     * @return list<int>
     */
    public function handle(Page $page, iterable $usages): array
    {
        $version = UsageVersion::workingCopyOf($page);
        $strips = PageStrips::fromArray($page->draft_strips ?? $page->strips);
        $places = [];

        foreach ($usages as $usage) {
            if ($usage->version === $version) {
                $places[] = [$strips->positionOf($usage->strip_id) ?? PHP_INT_MAX, $usage->id, $usage->media_id];
            }
        }

        sort($places);

        return array_values(array_unique(array_column($places, 2)));
    }
}
