<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The alt text of a file of the media library. How long it may be is a rule of UpdateAltText.
 */
final class UpdateAltTextRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alt.*' => $this->error('alt_text'),
        ];
    }

    /**
     * The text as the editor typed it; null takes the alt text away.
     */
    public function alt(): ?string
    {
        $alt = $this->input('alt');

        return is_string($alt) ? $alt : null;
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
