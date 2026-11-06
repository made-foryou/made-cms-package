<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\ScanDoubt;

/**
 * A strip the scan read on one page, before the pages are combined into strip types.
 *
 * @phpstan-import-type RecognisedFieldArray from RecognisedField
 *
 * @phpstan-type RecognisedStripArray array{
 *     fingerprint: string,
 *     hint: string|null,
 *     selector: string,
 *     doubts: list<string>,
 *     fields: list<RecognisedFieldArray>,
 * }
 */
final readonly class RecognisedStrip
{
    /**
     * @param  string  $fingerprint  its structure without texts and without the number of items; equal on every page
     * @param  string|null  $hint  the id (#over-ons) or a class (.hero) that can name its type
     * @param  string  $selector  its root in the document
     * @param  list<RecognisedField>  $fields  in the order of the document
     * @param  list<ScanDoubt>  $doubts
     */
    public function __construct(
        public string $fingerprint,
        public ?string $hint,
        public string $selector,
        public array $fields,
        public array $doubts = [],
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fingerprint: is_scalar($data['fingerprint'] ?? null) ? (string) $data['fingerprint'] : '',
            hint: is_string($data['hint'] ?? null) ? $data['hint'] : null,
            selector: is_scalar($data['selector'] ?? null) ? (string) $data['selector'] : '',
            fields: RecognisedField::list(is_array($data['fields'] ?? null) ? $data['fields'] : []),
            doubts: ScanDoubt::fromValues($data['doubts'] ?? []),
        );
    }

    /**
     * @return RecognisedStripArray
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'hint' => $this->hint,
            'selector' => $this->selector,
            'doubts' => ScanDoubt::values($this->doubts),
            'fields' => array_map(static fn (RecognisedField $field): array => $field->toArray(), $this->fields),
        ];
    }
}
