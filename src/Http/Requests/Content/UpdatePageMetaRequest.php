<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Http\Cms\MetaTexts;
use Made\CmsPackage\Http\Requests\LauncherRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * How Google sees a page: its title and description in Google, and the switches for the sitemap and noindex. A text
 * longer than Google shows is fine (the counter says so); only one far longer is refused.
 */
final class UpdatePageMetaRequest extends LauncherRequest
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
            inSitemap: $this->boolean('in_sitemap'),
            indexable: $this->boolean('indexable'),
            editorId: $this->editorId(),
        );
    }

    /**
     * A refusal goes back to the card, lower on the screen, where the message stands under its field.
     */
    protected function getRedirectUrl(): string
    {
        return Str::before(parent::getRedirectUrl(), '#').'#'.PageRedirect::GOOGLE;
    }
}
