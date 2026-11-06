<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Enums\ScanRole;

/**
 * A field of a strip type while the pages are combined: what it proposes and the value it read per instance.
 *
 * The values are kept by the index of their instance (a strip, or an item of a list over all strips); a list
 * keeps, per strip, the indexes of its items in the values of its own fields.
 */
final class Proposal
{
    public string $key = '';

    /**
     * @param  list<ScanDoubt>  $doubts
     * @param  array<int, mixed>  $values
     * @param  list<string>  $options
     * @param  list<Proposal>  $fields
     * @param  ScanRole|null  $name  the role it is named after, when that differs from the role of its type
     */
    public function __construct(
        public readonly ScanRole $role,
        public readonly string $selector,
        public readonly bool $required,
        public readonly array $doubts,
        public readonly array $values,
        public readonly array $options = [],
        public readonly ?string $item = null,
        public readonly ?int $max = null,
        public readonly array $fields = [],
        public readonly ?ScanRole $name = null,
    ) {}

    public function toField(): ScanField
    {
        $items = $this->role === ScanRole::Items;

        return new ScanField(
            key: $this->key,
            type: $this->role->type(),
            selector: $this->selector,
            required: $this->required,
            doubts: $this->doubts,
            options: $this->options,
            item: $this->item,
            min: $items ? 1 : null,
            max: $this->max,
            fields: array_map(static fn (self $field): ScanField => $field->toField(), $this->fields),
        );
    }

    /**
     * The values of one instance by key, in the stored form; a field without a value is left out.
     *
     * @param  list<Proposal>  $fields
     * @return array<string, mixed>
     */
    public static function valuesOf(array $fields, int $instance): array
    {
        $values = [];

        foreach ($fields as $field) {
            if (! array_key_exists($instance, $field->values)) {
                continue;
            }

            $value = $field->values[$instance];

            if ($field->role === ScanRole::Items) {
                $indexes = is_array($value) ? $value : [];
                $value = array_map(static fn (mixed $item): array => self::valuesOf($field->fields, is_int($item) ? $item : -1), array_values($indexes));
            }

            $values[$field->key] = $value;
        }

        return $values;
    }
}
