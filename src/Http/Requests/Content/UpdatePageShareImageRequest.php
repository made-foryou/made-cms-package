<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Support\Str;
use Made\CmsPackage\Http\Requests\LauncherRequest;
use Made\CmsPackage\Http\Responses\PageRedirect;

/**
 * The image of a shared link to a page, from the card "Hoe je link gedeeld wordt": the id of a file of the media
 * library, or empty for none of its own.
 */
final class UpdatePageShareImageRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'share_image' => ['present', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'share_image.*' => $this->error('share_image_invalid'),
        ];
    }

    public function mediaId(): ?int
    {
        return $this->filled('share_image') ? $this->integer('share_image') : null;
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::pages.errors.'.$key);
    }

    /**
     * Back to the card, where the refusal stands under the image.
     */
    protected function getRedirectUrl(): string
    {
        return Str::before(parent::getRedirectUrl(), '#').'#'.PageRedirect::SHARE;
    }
}
