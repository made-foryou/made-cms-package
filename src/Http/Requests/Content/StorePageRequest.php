<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\NewPage;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The new page of the wizard: its type, its name, optionally its slug, its parent and whether it is in the menu.
 */
final class StorePageRequest extends LauncherRequest
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
            'type' => ['nullable', 'string', 'max:100'],
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
            'type.*' => $this->error('type_invalid'),
        ];
    }

    public function toData(): NewPage
    {
        return new NewPage(
            title: $this->string('title')->toString(),
            slug: $this->filled('slug') ? $this->string('slug')->toString() : null,
            parentId: $this->filled('parent_id') ? $this->integer('parent_id') : null,
            inMenu: $this->boolean('in_menu'),
            editorId: $this->editorId(),
            type: $this->filled('type') ? $this->string('type')->toString() : null,
        );
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }
}
