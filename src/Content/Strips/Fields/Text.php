<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

/**
 * A single line of text, such as a heading or a label.
 */
final class Text extends Field
{
    public const int MAX_LENGTH = 255;

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), 'string', 'max:'.self::MAX_LENGTH]];
    }
}
