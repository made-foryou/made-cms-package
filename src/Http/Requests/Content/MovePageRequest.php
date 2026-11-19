<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The new place of a page in the tree of the pages screen: the page it goes under (none for the top) and its place
 * among the pages there, 0 for the first.
 */
final class MovePageRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer'],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.*' => $this->error('parent_invalid'),
            'position.*' => $this->error('position_invalid'),
        ];
    }

    public function parentId(): ?int
    {
        return $this->filled('parent_id') ? $this->integer('parent_id') : null;
    }

    public function position(): int
    {
        return $this->integer('position');
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }
}
