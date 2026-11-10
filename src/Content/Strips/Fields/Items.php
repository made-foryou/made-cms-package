<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Data\MediaFiles;

/**
 * A list of items that all have the same fields, such as buttons, cards or opening hours.
 *
 * The editor adds, removes and orders the items; required() asks for at least one.
 * Every stored item has an id of its own (a uuid) that stays the same when the list changes.
 */
final class Items extends Field
{
    /**
     * @var list<Field>
     */
    private array $fields = [];

    private ?int $min = null;

    private ?int $max = null;

    private ?string $itemLabel = null;

    protected mixed $default = [];

    /**
     * @param  list<Field>  $fields
     */
    public static function make(string $key, array $fields): self
    {
        $field = new self($key);
        $field->fields = $fields;

        return $field;
    }

    public function type(): string
    {
        return 'items';
    }

    public function min(int $min): self
    {
        $field = clone $this;
        $field->min = $min;

        return $field;
    }

    public function max(int $max): self
    {
        $field = clone $this;
        $field->max = $max;

        return $field;
    }

    /**
     * The name of one item, as in "Knop toevoegen".
     */
    public function itemLabel(string $label): self
    {
        $field = clone $this;
        $field->itemLabel = $label;

        return $field;
    }

    /**
     * @return list<Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getMin(): ?int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function getItemLabel(): string
    {
        return $this->itemLabel ?? 'Item';
    }

    protected function details(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
            'item_label' => $this->getItemLabel(),
            'fields' => array_map(static fn (Field $field): array => $field->describe(), $this->fields),
        ];
    }

    /**
     * The titles of the items, in order: per item the first of its fields with text.
     */
    public function summary(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        $titles = [];

        foreach ($value as $item) {
            foreach (is_array($item) ? $this->fields : [] as $field) {
                $title = $field->summary($item[$field->getKey()] ?? null);

                if ($title !== null) {
                    $titles[] = $title;

                    break;
                }
            }
        }

        return $titles === [] ? null : implode(', ', $titles);
    }

    public function rules(string $path): array
    {
        $list = [$this->presence(), 'array', 'list'];

        if ($this->min !== null) {
            $list[] = 'min:'.$this->min;
        }

        if ($this->max !== null) {
            $list[] = 'max:'.$this->max;
        }

        $keys = ['id', ...$this->keys()];
        $rules = [$path => $list, "{$path}.*" => ['array:'.implode(',', $keys)], "{$path}.*.id" => ['nullable', 'uuid']];

        foreach ($this->fields as $field) {
            $rules = [...$rules, ...$field->rules("{$path}.*.{$field->getKey()}")];
        }

        return $rules;
    }

    public function attributes(string $path): array
    {
        $attributes = [$path => $this->getLabel(), "{$path}.*" => $this->getItemLabel()];

        foreach ($this->fields as $field) {
            $attributes = [...$attributes, ...$field->attributes("{$path}.*.{$field->getKey()}")];
        }

        return $attributes;
    }

    /**
     * Every item with an id first: the id it has, or a new one when it has none or shares it with an item above.
     */
    public function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $seen = [];

        return array_map(function (mixed $item) use (&$seen): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $id = $item['id'] ?? null;

            if (! is_string($id) || $id === '' || isset($seen[$id])) {
                $id = (string) Str::uuid();
            }

            $seen[$id] = true;
            unset($item['id']);

            foreach ($this->fields as $field) {
                if (array_key_exists($field->getKey(), $item)) {
                    $item[$field->getKey()] = $field->normalize($item[$field->getKey()]);
                }
            }

            return ['id' => $id, ...$item];
        }, $value);
    }

    /**
     * The list the editor sends is the list: its order, its items, nothing more.
     *
     * An item with the id of a stored item keeps the fields it leaves out; an item without an id,
     * with an unknown id or with an id an item above already took is new and gets its id when it is stored.
     */
    public function merge(mixed $stored, mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $keys = array_flip($this->keys());
        $known = [];

        foreach (is_array($stored) ? $stored : [] as $item) {
            if (is_array($item) && is_string($item['id'] ?? null)) {
                $known[$item['id']] = array_intersect_key($item, $keys);
            }
        }

        return array_map(static function (mixed $item) use (&$known): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $id = $item['id'] ?? null;
            unset($item['id']);

            if (! is_string($id) || ! isset($known[$id])) {
                return $item;
            }

            $merged = ['id' => $id, ...$known[$id], ...$item];
            unset($known[$id]);

            return $merged;
        }, $value);
    }

    /**
     * Every item with the levels of the stored item of the same id; an item without a known id is new, so its
     * headings get their default level.
     */
    public function keepLevel(mixed $stored, mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $known = [];

        foreach (is_array($stored) ? $stored : [] as $item) {
            if (is_array($item) && is_string($item['id'] ?? null)) {
                $known[$item['id']] = $item;
            }
        }

        return array_map(function (mixed $item) use ($known): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $id = $item['id'] ?? null;
            $storedItem = is_string($id) ? ($known[$id] ?? []) : [];

            foreach ($this->fields as $field) {
                $key = $field->getKey();

                if (array_key_exists($key, $item)) {
                    $item[$key] = $field->keepLevel($storedItem[$key] ?? null, $item[$key]);
                }
            }

            return $item;
        }, $value);
    }

    /**
     * Always a list: every item with its id (null for an item stored without one) and every field rendered,
     * a missing one from its default.
     *
     * @return list<array<string, mixed>>
     */
    public function render(mixed $value, ?MediaFiles $media = null): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $rendered = ['id' => is_string($item['id'] ?? null) ? $item['id'] : null];

            foreach ($this->fields as $field) {
                $key = $field->getKey();
                $rendered[$key] = $field->render(array_key_exists($key, $item) ? $item[$key] : $field->getDefault(), $media);
            }

            $items[] = $rendered;
        }

        return $items;
    }

    /**
     * The files of every item, in order, by the position of the item in the list: "kaarten.2.foto".
     */
    public function places(mixed $value, string $path): array
    {
        $places = [];

        foreach (is_array($value) ? $value : [] as $position => $item) {
            foreach (is_array($item) ? $this->fields : [] as $field) {
                $key = $field->getKey();
                $places = [...$places, ...$field->places($item[$key] ?? null, "{$path}.{$position}.{$key}")];
            }
        }

        return $places;
    }

    /**
     * An item by its label and its number ("Foto 3" for position 2), then the labels of the field in it.
     */
    public function placeLabels(array $rest): ?array
    {
        $position = $rest[0] ?? '';
        $key = $rest[1] ?? null;

        if (! ctype_digit($position) || $key === null) {
            return null;
        }

        foreach ($this->fields as $field) {
            if ($field->getKey() === $key) {
                $labels = $field->placeLabels(array_slice($rest, 2));

                return $labels === null ? null : [$this->getItemLabel().' '.((int) $position + 1), ...$labels];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        return array_map(static fn (Field $field): string => $field->getKey(), $this->fields);
    }
}
