<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\DescriptionSources;
use Made\CmsPackage\Content\Data\DescriptionSuggestion;
use Made\CmsPackage\Content\Enums\MetaField;
use Made\CmsPackage\Content\Models\Page;

final class SuggestPageDescription
{
    use AsAction;

    public function __construct(
        private readonly DescriptionSources $sources,
    ) {}

    /**
     * A description in Google for the page, from the first source (DescriptionSources) that proposes one that fits on
     * one line; a proposal that is empty or longer than Google shows is passed by, whatever source gives it. Null when
     * no source has anything sensible: the screen then proposes nothing.
     */
    public function handle(Page $page): ?DescriptionSuggestion
    {
        foreach ($this->sources->sources as $source) {
            $suggestion = $source->suggest($page);

            if ($suggestion === null) {
                continue;
            }

            $text = Str::squish($suggestion->text);
            $length = MeasureMeta::run(MetaField::Description, $text);

            if (! $length->missing && ! $length->tooLong) {
                return new DescriptionSuggestion($text, $suggestion->origin);
            }
        }

        return null;
    }
}
