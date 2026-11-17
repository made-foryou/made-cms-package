<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Made\CmsPackage\Content\Strips\Emphasis;

/**
 * A single line of text, such as a heading or a label.
 *
 * With emphasis() an editor marks a word with asterisks ("Jullie dag in *vertrouwde* handen", see Emphasis). The value
 * keeps the asterisks; the view prints the parts with emphasis as an <em>.
 */
final class Text extends Field
{
    public const int MAX_LENGTH = 255;

    private bool $emphasis = false;

    public static function make(string $key): self
    {
        return new self($key);
    }

    /**
     * Whether a word between asterisks is emphasis: the side panel says so, and watching along shows it as an <em>.
     */
    public function emphasis(bool $emphasis = true): self
    {
        $field = clone $this;
        $field->emphasis = $emphasis;

        return $field;
    }

    public function hasEmphasis(): bool
    {
        return $this->emphasis;
    }

    public function type(): string
    {
        return 'text';
    }

    protected function details(): array
    {
        return ['max_length' => self::MAX_LENGTH, 'emphasis' => $this->emphasis];
    }

    /**
     * The text on one line, without the asterisks of its emphasis.
     */
    public function summary(mixed $value): ?string
    {
        return self::line($this->emphasis && is_string($value) ? Emphasis::plain($value) : $value);
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), 'string', 'max:'.self::MAX_LENGTH]];
    }
}
