<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\PageChanges;
use Made\CmsPackage\Content\Data\PageTypeChoice;
use Made\CmsPackage\Content\Data\ScheduleChoice;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The basics of a page: its name, its slug, its parent, whether it is in the menu, whether visitors see it, its type
 * and when its draft goes live. The form of the home page has no slug, no parent and no switch for visitors, so the page
 * keeps those; a form without a choice of type (a site without page types) keeps the type, and a form without the
 * switch "Publiceren op een moment" keeps the planned publication. The moment is read in the timezone of the app, as
 * the field shows it.
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
            'type' => ['nullable', 'string', 'max:100'],
            'scheduled' => ['sometimes', 'boolean'],
            'publish_at' => ['exclude_unless:scheduled,1', 'required', 'date_format:Y-m-d\\TH:i,Y-m-d\\TH:i:s'],
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
            'publish_at.*' => $this->error('publish_at_invalid'),
        ];
    }

    public function toData(Page $page): PageChanges
    {
        return new PageChanges(
            title: $this->string('title')->toString(),
            slug: $this->has('slug') ? ($this->filled('slug') ? $this->string('slug')->toString() : null) : $page->slug,
            parentId: $this->has('parent_id') ? ($this->filled('parent_id') ? $this->integer('parent_id') : null) : $page->parent_id,
            inMenu: $this->boolean('in_menu'),
            hidden: $this->has('visible') ? ! $this->boolean('visible') : null,
            editorId: $this->editorId(),
            type: $this->has('type') ? new PageTypeChoice($this->filled('type') ? $this->string('type')->toString() : null) : null,
            schedule: $this->has('scheduled') ? new ScheduleChoice($this->boolean('scheduled') ? $this->publishAt() : null) : null,
        );
    }

    private function publishAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('publish_at')->toString(), config()->string('app.timezone'));
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }
}
