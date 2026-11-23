<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * A page type of the site breaks the rules of a definition; a mistake of the developer, not of an editor.
 */
final class InvalidPageType extends RuntimeException
{
    public static function key(string $type, string $key): self
    {
        return new self("Page type [{$type}] has an invalid key [{$key}]; use lowercase letters, digits and dashes.");
    }

    public static function noStrips(string $type): self
    {
        return new self("Page type [{$type}] allows no strip type. Return null from strips() to allow every strip type.");
    }

    public static function unknownStrip(string $type, string $strip): self
    {
        return new self("Page type [{$type}] names the strip type [{$strip}], which is not in the catalogue.");
    }

    public static function defaultNotAllowed(string $type, string $strip): self
    {
        return new self("Page type [{$type}] starts with the strip type [{$strip}], which it does not allow.");
    }
}
