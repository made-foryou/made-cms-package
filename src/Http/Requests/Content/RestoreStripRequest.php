<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * A removed strip the edit shell puts back with "Ongedaan maken": the strip as the server answered it when it was
 * removed, and the place it had. RestoreStrip checks the values against the strip type.
 */
final class RestoreStripRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'visible' => ['required', 'boolean'],
            'values' => ['present', 'array'],
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
            'visible.*' => $this->error('visible'),
            'values.*' => $this->error('values'),
            'position.*' => $this->error('position'),
        ];
    }

    public function toData(string $id): Strip
    {
        /** @var array<string, mixed> $values */
        $values = $this->array('values');

        return new Strip($id, $this->string('type')->toString(), $this->boolean('visible'), $values);
    }

    /**
     * Whether the editor chooses the level of a heading; for anyone else the Action keeps the stored level.
     */
    public function choosesLevels(): bool
    {
        return Visitor::choosesHeadingLevels($this);
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
