<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Whether visitors see a page, from the buttons "Verbergen" and "Weer tonen" in the card Weghalen.
 */
final class UpdatePageVisibilityRequest extends LauncherRequest
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
            'visible.*' => $this->error('visible_invalid'),
        ];
    }

    public function hidden(): bool
    {
        return ! $this->boolean('visible');
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }
}
