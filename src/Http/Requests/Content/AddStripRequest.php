<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * A new strip from the catalogue of the edit shell: its type and the place it gets on the page.
 */
final class AddStripRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.*' => $this->error('type'),
            'position.*' => $this->error('position'),
        ];
    }

    public function type(): string
    {
        return $this->string('type')->toString();
    }

    public function position(): int
    {
        return $this->integer('position');
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::cms.edit.errors.'.$key);
    }
}
