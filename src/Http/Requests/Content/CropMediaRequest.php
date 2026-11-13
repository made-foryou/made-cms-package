<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Illuminate\Validation\Rule;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * A crop as the crop step sends it: the area in pixels of the original as the editor saw it, its turn and mirror, the
 * focal point and whether the photo is scaled down. Whether the area lies inside the photo is a rule of CropImage.
 */
final class CropMediaRequest extends LauncherRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'crop' => ['required', 'array'],
            'crop.x' => ['required', 'integer', 'min:0'],
            'crop.y' => ['required', 'integer', 'min:0'],
            'crop.width' => ['required', 'integer', 'min:1'],
            'crop.height' => ['required', 'integer', 'min:1'],
            'crop.rotation' => ['required', 'integer', Rule::in(CropArea::ROTATIONS)],
            'crop.flip' => ['required', 'boolean'],
            'focal' => ['required', 'array'],
            'focal.x' => ['required', 'numeric', 'between:0,1'],
            'focal.y' => ['required', 'numeric', 'between:0,1'],
            'scale_down' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = $this->error('crop');

        return ['crop' => $message, 'crop.*' => $message, 'focal' => $message, 'focal.*' => $message, 'scale_down.*' => $message];
    }

    public function toData(): CropMedia
    {
        return new CropMedia(
            area: new CropArea(
                x: $this->integer('crop.x'),
                y: $this->integer('crop.y'),
                width: $this->integer('crop.width'),
                height: $this->integer('crop.height'),
                rotation: $this->integer('crop.rotation'),
                flip: $this->boolean('crop.flip'),
            ),
            focalX: $this->float('focal.x'),
            focalY: $this->float('focal.y'),
            scaleDown: $this->boolean('scale_down'),
        );
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::media.errors.'.$key);
    }
}
