<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The strips of the working copy in the edit shell, in their stored order: the place of a strip in this list is
 * the position the strip Actions take.
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
