<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\MetaSearch;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Enums\MetaFilter;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\MetaLinks;
use Made\CmsPackage\Http\Cms\MetaTexts;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The title and the description of a page from the Meta-overzicht, with the filter of the list and the points this
 * round put in order, so the screen goes on where the editor was. The switches of the page stay as they are. A text
 * longer than Google shows is fine (the bar says so); only one far longer is refused, as on the card of the page.
 */
final class UpdateMetaTagsRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return MetaTexts::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return MetaTexts::messages();
    }

    public function toData(): PageMetaChanges
    {
        return new PageMetaChanges(
            title: $this->string('meta_title')->toString(),
            description: $this->string('meta_description')->toString(),
            editorId: $this->editorId(),
        );
    }

    /**
     * The filter of the list, as the form holds it; one the screen does not know lets the screen choose.
     */
    public function filter(): ?MetaFilter
    {
        $filter = $this->input('filter');

        return is_string($filter) ? MetaFilter::tryFrom($filter) : null;
    }

    /**
     * The points this round put in order, as the form holds it; anything else starts at none.
     */
    public function done(): int
    {
        $done = $this->input('done');
        $done = is_string($done) ? filter_var($done, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => MetaSearch::MAX_DONE]]) : false;

        return $done === false ? 0 : $done;
    }

    /**
     * A refusal goes back to the page in the panel, where the message stands under its field.
     */
    protected function getRedirectUrl(): string
    {
        $page = $this->route('page');

        return MetaLinks::screen($this->filter(), $page instanceof Page ? $page->id : null, $this->done(), panel: true);
    }
}
