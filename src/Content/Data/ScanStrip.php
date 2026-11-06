<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A strip the scan found on a page, with the values it read in the stored form of the fields of its type.
 *
 * @phpstan-type ScanStripArray array{type: string, selector: string, values: array<string, mixed>}
 */
final readonly class ScanStrip
{
    /**
     * @param  string  $type  the key of its ScanType
     * @param  string  $selector  the root of the strip in the document
     * @param  array<string, mixed>  $values  by field key; an optional field without a value is left out
     */
    public function __construct(
        public string $type,
        public string $selector,
        public array $values,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $values = is_array($data['values'] ?? null) ? $data['values'] : [];

        /** @var array<string, mixed> $values */
        return new self(
            type: is_scalar($data['type'] ?? null) ? (string) $data['type'] : '',
            selector: is_scalar($data['selector'] ?? null) ? (string) $data['selector'] : '',
            values: $values,
        );
    }

    /**
     * @return ScanStripArray
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'selector' => $this->selector,
            'values' => $this->values,
        ];
    }
}
