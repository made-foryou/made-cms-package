<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The values of a strip from the side panel: always every field, checked against the strip type by UpdateStrip.
 */
final class UpdateStripRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'values' => ['present', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.*' => $this->error('values'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        /** @var array<string, mixed> */
        return $this->array('values');
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::cms.edit.errors.'.$key);
    }
}
