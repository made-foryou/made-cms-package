<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\FieldType;

/**
 * One field of a strip type: what an editor fills in, and for a list the fields of each item.
 */
final readonly class Field
{
    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public string $label,
        public array $fields = [],
    ) {}
}
