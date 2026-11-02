<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

/**
 * A list of items that all have the same fields, such as buttons, cards or opening hours.
 *
 * The editor adds, removes and orders the items; required() asks for at least one.
 */
final class Items extends Field
{
    /**
     * @var list<Field>
     */
    private array $fields = [];

    private ?int $min = null;

    private ?int $max = null;

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

    public function rules(string $path): array
    {
        $list = [$this->presence(), 'array', 'list'];

        if ($this->min !== null) {
            $list[] = 'min:'.$this->min;
        }

        if ($this->max !== null) {
            $list[] = 'max:'.$this->max;
        }

        $keys = array_map(static fn (Field $field): string => $field->getKey(), $this->fields);
        $rules = [$path => $list, "{$path}.*" => ['array:'.implode(',', $keys)]];

        foreach ($this->fields as $field) {
            $rules = [...$rules, ...$field->rules("{$path}.*.{$field->getKey()}")];
        }

        return $rules;
    }

    public function attributes(string $path): array
    {
        $attributes = [$path => $this->getLabel()];

        foreach ($this->fields as $field) {
            $attributes = [...$attributes, ...$field->attributes("{$path}.*.{$field->getKey()}")];
        }

        return $attributes;
    }

    public function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_map(function (mixed $item): mixed {
            if (! is_array($item)) {
                return $item;
            }

            foreach ($this->fields as $field) {
                if (array_key_exists($field->getKey(), $item)) {
                    $item[$field->getKey()] = $field->normalize($item[$field->getKey()]);
                }
            }

            return $item;
        }, $value);
    }
}
