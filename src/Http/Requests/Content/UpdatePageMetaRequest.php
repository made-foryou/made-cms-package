<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Enums\MetaField;
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
        return [
            'meta_title' => ['nullable', 'string', 'max:'.MetaField::Title->maxLength()],
            'meta_description' => ['nullable', 'string', 'max:'.MetaField::Description->maxLength()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'meta_title.*' => $this->error('meta_title_long'),
            'meta_description.*' => $this->error('meta_description_long'),
        ];
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

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }

    /**
     * A refusal goes back to the card, lower on the screen, where the message stands under its field.
     */
    protected function getRedirectUrl(): string
    {
        return Str::before(parent::getRedirectUrl(), '#').'#'.PageRedirect::GOOGLE;
    }
}
