<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A kind of strip the pages of a site are built from, defined in resources/cms/strips/<key>.json in the host.
 */
final readonly class StripType
{
    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $fields,
    ) {}
}
