<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Scan\TypeMerger;

/**
 * Combines what the scan read on the pages of a site into its result: the strip types it proposes, with their
 * fields and doubts, and per page the strips with their values.
 *
 * Strips with the same structure are one type, on every page. What is static design text, what a choice and
 * what required follows from all instances together, so it is decided here and not per page.
 */
final class CombinePageScans
{
    use AsAction;

    /**
     * @param  list<RecognisedPage>  $pages  a page the list holds twice counts once
     */
    public function handle(array $pages): ScanResult
    {
        return (new TypeMerger)->merge($pages);
    }
}
