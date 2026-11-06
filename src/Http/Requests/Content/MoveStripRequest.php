<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The new place of a strip on the page, from the opbouw of the edit shell.
 */
final class MoveStripRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'position.*' => $this->error('position'),
        ];
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
