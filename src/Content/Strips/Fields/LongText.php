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

    public function type(): string
    {
        return 'long_text';
    }

    protected function details(): array
    {
        return ['max_length' => self::MAX_LENGTH];
    }

    /**
     * The first line with text in it.
     */
    public function summary(mixed $value): ?string
    {
        foreach (is_string($value) ? preg_split('/\R/u', $value) ?: [] : [] as $line) {
            if (($summary = self::line($line)) !== null) {
                return $summary;
            }
        }

        return null;
    }

    /**
     * Every line with text in it: a long text is running text.
     */
    public function prose(mixed $value): ?string
    {
        return is_string($value) ? self::paragraphs($value) : null;
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), 'string', 'max:'.self::MAX_LENGTH]];
    }
}
