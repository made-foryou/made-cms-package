<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\PageChanges;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The basics of a page: its name, its slug, its parent and whether it is in the menu. The form of the home page has
 * no slug and no parent, so the page keeps those.
 */
final class UpdatePageRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => $this->error('title_long'),
            'title.*' => $this->error('title_required'),
            'slug.*' => $this->error('slug_invalid'),
            'parent_id.*' => $this->error('parent_invalid'),
        ];
    }

    public function toData(Page $page): PageChanges
    {
        return new PageChanges(
            title: $this->string('title')->toString(),
            slug: $this->has('slug') ? ($this->filled('slug') ? $this->string('slug')->toString() : null) : $page->slug,
            parentId: $this->has('parent_id') ? ($this->filled('parent_id') ? $this->integer('parent_id') : null) : $page->parent_id,
            inMenu: $this->boolean('in_menu'),
        );
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }
}
