<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\RecognisedStrip;
use Made\CmsPackage\Content\Data\ScannedPage;
use Made\CmsPackage\Content\Scan\Fingerprint;
use Made\CmsPackage\Content\Scan\Html;
use Made\CmsPackage\Content\Scan\Names;
use Made\CmsPackage\Content\Scan\Selector;
use Made\CmsPackage\Content\Scan\StripFinder;

/**
 * Reads one rendered page for the scan: its title and its strips, each with its fields, values and doubts.
 *
 * It only reads the markup, deterministic and without any service: the same HTML gives the same result. What
 * needs every page, such as the strip types and their names, is decided when the pages are combined.
 */
final class RecognisePage
{
    use AsAction;

    public function handle(ScannedPage $page): RecognisedPage
    {
        $found = (new StripFinder)->find(Html::parse($page->html));
        $strips = [];

        foreach ($found['strips'] as $element) {
            $strips[] = new RecognisedStrip(
                fingerprint: Fingerprint::of($element),
                hint: Names::hint($element),
                selector: Selector::absolute($element),
                fields: [],
            );
        }

        return new RecognisedPage($page->url, $found['title'], $strips);
    }
}
