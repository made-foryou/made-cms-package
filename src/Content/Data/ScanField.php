<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\ScanDoubt;

/**
 * A field the scan proposes for a strip type, or for the items of a list in it.
 *
 * The selector leads from the root of the strip (or of one item) to the element of the field: steps of a tag,
 * with :nth-of-type(n) from the second element of that tag on, joined by " > ". An empty selector is the root itself.
 *
 * @phpstan-type ScanFieldArray array{
 *     key: string,
 *     type: string,
 *     selector: string,
 *     required: bool,
 *     certain: bool,
 *     doubts: list<string>,
 *     options?: list<string>,
 *     item?: string|null,
 *     min?: int|null,
 *     max?: int|null,
 *     fields?: list<array<string, mixed>>,
 *     tag?: string|null,
 *     emphasis?: bool,
 * }
 */
final readonly class ScanField
{
    /**
     * @param  string  $type  the type of the strip field, as Field::type() names it, or image
     * @param  list<ScanDoubt>  $doubts
     * @param  list<string>  $options  the options of a choice
     * @param  string|null  $item  the tag of one item of a list
     * @param  list<ScanField>  $fields  the fields of every item of a list
     * @param  string|null  $tag  the level of a heading: the tag most of its examples have
     * @param  bool  $emphasis  whether a text or heading has emphasis: words between asterisks are an <em>
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $selector,
        public bool $required = true,
        public array $doubts = [],
        public array $options = [],
        public ?string $item = null,
        public ?int $min = null,
        public ?int $max = null,
        public array $fields = [],
        public ?string $tag = null,
        public bool $emphasis = false,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $options = [];

        foreach (is_array($data['options'] ?? null) ? $data['options'] : [] as $option) {
            if (is_scalar($option)) {
                $options[] = (string) $option;
            }
        }

        $fields = [];

        foreach (is_array($data['fields'] ?? null) ? $data['fields'] : [] as $field) {
            if (is_array($field)) {
                $fields[] = self::fromArray($field);
            }
        }

        return new self(
            key: is_scalar($data['key'] ?? null) ? (string) $data['key'] : '',
            type: is_scalar($data['type'] ?? null) ? (string) $data['type'] : '',
            selector: is_scalar($data['selector'] ?? null) ? (string) $data['selector'] : '',
            required: (bool) ($data['required'] ?? true),
            doubts: ScanDoubt::fromValues($data['doubts'] ?? []),
            options: $options,
            item: is_string($data['item'] ?? null) ? $data['item'] : null,
            min: is_int($data['min'] ?? null) ? $data['min'] : null,
            max: is_int($data['max'] ?? null) ? $data['max'] : null,
            fields: $fields,
            tag: is_string($data['tag'] ?? null) ? $data['tag'] : null,
            emphasis: ($data['emphasis'] ?? false) === true,
        );
    }

    public function certain(): bool
    {
        return $this->doubts === [];
    }

    /**
     * Only a choice has options, only a heading a level, and only a list has an item tag, a range and fields of its
     * own. Emphasis is written only for a text or heading that has it.
     *
     * @return ScanFieldArray
     */
    public function toArray(): array
    {
        $array = [
            'key' => $this->key,
            'type' => $this->type,
            'selector' => $this->selector,
            'required' => $this->required,
            'certain' => $this->certain(),
            'doubts' => ScanDoubt::values($this->doubts),
        ];

        if ($this->type === 'choice') {
            $array['options'] = $this->options;
        }

        if ($this->type === 'heading') {
            $array['tag'] = $this->tag;
        }

        if ($this->emphasis && in_array($this->type, ['text', 'heading'], true)) {
            $array['emphasis'] = true;
        }

        if ($this->type === 'items') {
            $array['item'] = $this->item;
            $array['min'] = $this->min;
            $array['max'] = $this->max;
            $array['fields'] = array_map(static fn (self $field): array => $field->toArray(), $this->fields);
        }

        return $array;
    }
}
