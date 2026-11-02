<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

/**
 * Plain text over several lines, without markup.
 */
final class LongText extends Field
{
    public const int MAX_LENGTH = 65535;

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), 'string', 'max:'.self::MAX_LENGTH]];
    }
}
