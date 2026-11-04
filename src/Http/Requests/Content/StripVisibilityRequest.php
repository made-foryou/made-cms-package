<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Whether a strip shows on the site, from the switch in the side panel.
 */
final class StripVisibilityRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'visible' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visible.*' => $this->error('visible'),
        ];
    }

    public function visible(): bool
    {
        return $this->boolean('visible');
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::cms.edit.errors.'.$key);
    }
}
