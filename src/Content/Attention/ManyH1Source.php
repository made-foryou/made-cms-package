<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Actions\CheckShownInGoogle;
use Made\CmsPackage\Content\Actions\ListShownStrips;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

/**
 * A page Google shows whose strips give a visitor more than one h1, also in a list: Google reads one main subject.
 *
 * It counts the headings of the published strips a visitor sees as they render (StripType::headingLevels()); the
 * draft does not count, the overview Koppen of the edit shell shows that one. Only the strips are known here: the site
 * may print an h1 of its own, so "no h1" is no point the server can make.
 */
final readonly class ManyH1Source implements AttentionSource
{
    private const string MAIN = 'h1';

    public function items(Page $page, AttentionContext $context): array
    {
        if (! CheckShownInGoogle::run($page)) {
            return [];
        }

        $count = 0;
        $names = [];

        foreach (ListShownStrips::run(PageStrips::fromArray($page->strips)) as $shown) {
            $main = count(array_keys($shown->type->headingLevels($shown->strip->values), self::MAIN, true));

            if ($main > 0) {
                $count += $main;
                $names[] = $shown->type->label();
            }
        }

        if ($count < 2) {
            return [];
        }

        return [new AttentionItem(AttentionReason::ManyH1, AttentionSeverity::Medium, ['count' => $count], array_values(array_unique($names)))];
    }
}
