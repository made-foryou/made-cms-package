<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips;

use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\StripType;

/**
 * A strip type built in a test, to try one definition without a class per case.
 */
final class InlineStripType extends StripType
{
    /**
     * @param  list<Field>  $fields
     * @param  string|null  $stripLabel  the name in the catalogue, else the one from the class name
     */
    public function __construct(
        private readonly string $stripKey,
        private readonly array $fields,
        private readonly ?string $stripLabel = null,
    ) {}

    public function key(): string
    {
        return $this->stripKey;
    }

    public function label(): string
    {
        return $this->stripLabel ?? parent::label();
    }

    public function fields(): array
    {
        return $this->fields;
    }
}
