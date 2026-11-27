<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Suggestions;

use Made\CmsPackage\Content\Actions\ListShownStrips;
use Made\CmsPackage\Content\Actions\TakeWholeSentences;
use Made\CmsPackage\Content\Data\DescriptionSuggestion;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\MetaField;
use Made\CmsPackage\Content\Enums\SuggestionOrigin;
use Made\CmsPackage\Content\Models\Page;

/**
 * A description from the text of the page: the first whole sentences of the first long or rich text a visitor sees
 * (the published strips, never the draft) that fit in what Google shows of a description.
 */
final readonly class PageTextDescriptionSource implements DescriptionSource
{
    /**
     * Fewer characters say too little of a page to be a description ("Kom langs.").
     */
    public const int SHORTEST = 30;

    public function suggest(Page $page): ?DescriptionSuggestion
    {
        foreach (ListShownStrips::run(PageStrips::fromArray($page->strips)) as $shown) {
            foreach ($shown->type->prose($shown->strip->values) as $text) {
                $sentences = TakeWholeSentences::run($text, MetaField::Description->limit());

                if ($sentences !== null && mb_strlen($sentences) >= self::SHORTEST) {
                    return new DescriptionSuggestion($sentences, SuggestionOrigin::PageText);
                }
            }
        }

        return null;
    }
}
