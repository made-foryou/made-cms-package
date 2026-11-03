<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip of the working copy as the edit shell shows it: which strip it is and what its type is called.
 *
 * @phpstan-type EditableStripArray array{id: string, type: string, label: string, visible: bool, fields: int}
 */
final readonly class EditableStrip
{
    /**
     * @param  string  $label  the name of the strip type editors see
     * @param  int  $fields  the number of fields of the strip type
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $label,
        public bool $visible,
        public int $fields,
    ) {}

    /**
     * @return EditableStripArray
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'visible' => $this->visible,
            'fields' => $this->fields,
        ];
    }
}
