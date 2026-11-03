<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The strips an editor can choose in the edit shell, in the order of the working copy.
 *
 * @phpstan-import-type EditableStripArray from EditableStrip
 */
final readonly class EditableStrips
{
    /**
     * @param  list<EditableStrip>  $strips
     */
    public function __construct(
        public array $strips,
    ) {}

    /**
     * @return list<EditableStripArray>
     */
    public function toArray(): array
    {
        return array_map(static fn (EditableStrip $strip): array => $strip->toArray(), $this->strips);
    }
}
