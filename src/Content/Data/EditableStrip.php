<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip of the working copy as the edit shell shows it: which strip it is, what its type is called and a few words
 * of what it holds. A strip whose type left the catalogue is not known: its content is kept, the site does not show it.
 *
 * @phpstan-type EditableStripArray array{id: string, type: string, label: string, visible: bool, known: bool, fields: int, summary: string|null}
 */
final readonly class EditableStrip
{
    /**
     * @param  string  $label  the name of the strip type editors see; the stored key for an unknown type
     * @param  bool  $known  whether the type of the strip is in the catalogue
     * @param  int  $fields  the number of fields of the strip type
     * @param  string|null  $summary  the strip in a few words (StripType::summary()), null when it holds no text yet
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $label,
        public bool $visible,
        public bool $known,
        public int $fields,
        public ?string $summary,
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
            'known' => $this->known,
            'fields' => $this->fields,
            'summary' => $this->summary,
        ];
    }
}
