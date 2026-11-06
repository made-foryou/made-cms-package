<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\ScanDoubt;

/**
 * A strip type the scan proposes: the strips with the same structure on every scanned page.
 *
 * @phpstan-import-type ScanFieldArray from ScanField
 *
 * @phpstan-type ScanTypeArray array{
 *     key: string,
 *     label: string,
 *     hint: string|null,
 *     fingerprint: string,
 *     count: int,
 *     certain: bool,
 *     doubts: list<string>,
 *     fields: list<ScanFieldArray>,
 * }
 */
final readonly class ScanType
{
    /**
     * @param  string|null  $hint  where the name comes from: the id (#over-ons) or a class (.hero) of the strip
     * @param  string  $fingerprint  the structure of the strip without its texts and the number of its items
     * @param  int  $count  the number of strips of this type on all scanned pages
     * @param  list<ScanField>  $fields
     * @param  list<ScanDoubt>  $doubts
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $hint,
        public string $fingerprint,
        public int $count,
        public array $fields,
        public array $doubts = [],
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $fields = [];

        foreach (is_array($data['fields'] ?? null) ? $data['fields'] : [] as $field) {
            if (is_array($field)) {
                $fields[] = ScanField::fromArray($field);
            }
        }

        return new self(
            key: is_scalar($data['key'] ?? null) ? (string) $data['key'] : '',
            label: is_scalar($data['label'] ?? null) ? (string) $data['label'] : '',
            hint: is_string($data['hint'] ?? null) ? $data['hint'] : null,
            fingerprint: is_scalar($data['fingerprint'] ?? null) ? (string) $data['fingerprint'] : '',
            count: is_int($data['count'] ?? null) ? $data['count'] : 0,
            fields: $fields,
            doubts: ScanDoubt::fromValues($data['doubts'] ?? []),
        );
    }

    /**
     * Sure of the type itself; its fields have doubts of their own.
     */
    public function certain(): bool
    {
        return $this->doubts === [];
    }

    /**
     * @return ScanTypeArray
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'hint' => $this->hint,
            'fingerprint' => $this->fingerprint,
            'count' => $this->count,
            'certain' => $this->certain(),
            'doubts' => ScanDoubt::values($this->doubts),
            'fields' => array_map(static fn (ScanField $field): array => $field->toArray(), $this->fields),
        ];
    }
}
