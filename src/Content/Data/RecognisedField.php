<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Enums\ScanRole;

/**
 * An element the scan read on one page, before the pages are combined: its role, where it is and what it holds.
 *
 * The value has the stored form of the field the role proposes; an icon holds its class tokens and a list holds
 * its items instead, each a list of fields with selectors from the root of that item. A heading with an <em> holds
 * it between asterisks and has emphasis.
 *
 * @phpstan-type RecognisedFieldArray array{
 *     role: string,
 *     selector: string,
 *     value: mixed,
 *     doubts: list<string>,
 *     item?: string|null,
 *     items?: list<list<array<string, mixed>>>,
 *     emphasis?: bool,
 * }
 */
final readonly class RecognisedField
{
    /**
     * @param  list<ScanDoubt>  $doubts
     * @param  string|null  $item  the tag of one item of a list
     * @param  list<list<RecognisedField>>  $items  the fields of every item of a list
     * @param  bool  $emphasis  whether a heading holds an <em>, as words between asterisks in its text
     */
    public function __construct(
        public ScanRole $role,
        public string $selector,
        public mixed $value,
        public array $doubts = [],
        public ?string $item = null,
        public array $items = [],
        public bool $emphasis = false,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $role = is_string($data['role'] ?? null) ? ScanRole::tryFrom($data['role']) : null;

        if (! $role instanceof ScanRole) {
            return null;
        }

        $items = [];

        foreach (is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (is_array($item)) {
                $items[] = self::list($item);
            }
        }

        return new self(
            role: $role,
            selector: is_scalar($data['selector'] ?? null) ? (string) $data['selector'] : '',
            value: $data['value'] ?? null,
            doubts: ScanDoubt::fromValues($data['doubts'] ?? []),
            item: is_string($data['item'] ?? null) ? $data['item'] : null,
            items: $items,
            emphasis: ($data['emphasis'] ?? false) === true,
        );
    }

    /**
     * @param  array<mixed>  $fields
     * @return list<self>
     */
    public static function list(array $fields): array
    {
        $list = [];

        foreach ($fields as $field) {
            $read = is_array($field) ? self::fromArray($field) : null;

            if ($read instanceof self) {
                $list[] = $read;
            }
        }

        return $list;
    }

    /**
     * @return RecognisedFieldArray
     */
    public function toArray(): array
    {
        $array = [
            'role' => $this->role->value,
            'selector' => $this->selector,
            'value' => $this->value,
            'doubts' => ScanDoubt::values($this->doubts),
        ];

        if ($this->role === ScanRole::Items) {
            $array['item'] = $this->item;
            $array['items'] = array_map(
                static fn (array $fields): array => array_map(static fn (self $field): array => $field->toArray(), $fields),
                $this->items,
            );
        }

        if ($this->emphasis) {
            $array['emphasis'] = true;
        }

        return $array;
    }
}
