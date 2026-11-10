<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip of the working copy as the side panel builds it: the fields of its type and the values it holds.
 *
 * @phpstan-type StripDescriptionArray array{id: string, type: string, label: string, visible: bool, fields: list<array<string, mixed>>, values: array<string, mixed>}
 */
final readonly class StripDescription
{
    /**
     * @param  string  $label  the name of the strip type editors see
     * @param  list<array<string, mixed>>  $fields  the description of every field, in the order of the panel
     * @param  array<string, mixed>  $values  the stored value of every field, a missing one from its default
     * @param  MediaFiles  $media  the files of the media library its images hold; a file that is gone is not in it
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $label,
        public bool $visible,
        public array $fields,
        public array $values,
        public MediaFiles $media = new MediaFiles,
    ) {}

    /**
     * @return StripDescriptionArray
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'visible' => $this->visible,
            'fields' => $this->fields,
            'values' => $this->values,
        ];
    }
}
