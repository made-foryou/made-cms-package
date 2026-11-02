<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * A strip type of the site breaks the rules of a definition; a mistake of the developer, not of an editor.
 */
final class InvalidStripType extends RuntimeException
{
    public static function key(string $type, string $key): self
    {
        return new self("Strip type [{$type}] has an invalid key [{$key}]; use lowercase letters, digits and dashes.");
    }

    public static function fieldKey(string $type, string $field): self
    {
        return new self("Strip type [{$type}] has an invalid field key [{$field}]; use lowercase letters, digits and underscores.");
    }

    public static function reservedField(string $type, string $field): self
    {
        return new self("Strip type [{$type}] may not use the reserved field [{$field}].");
    }

    public static function duplicateField(string $type, string $field): self
    {
        return new self("Strip type [{$type}] has the field [{$field}] twice.");
    }

    public static function range(string $type, string $field): self
    {
        return new self("Strip type [{$type}] field [{$field}] has an invalid min or max.");
    }

    public static function emptyChoice(string $type, string $field): self
    {
        return new self("Strip type [{$type}] field [{$field}] has no options.");
    }

    public static function emptyItems(string $type, string $field): self
    {
        return new self("Strip type [{$type}] field [{$field}] has no fields.");
    }

    public static function nestedItems(string $type, string $field): self
    {
        return new self("Strip type [{$type}] field [{$field}] is a list inside a list.");
    }
}
