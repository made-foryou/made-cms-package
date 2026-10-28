<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;

/**
 * Checks the content of a strip against its type and returns it with every field of the type, in the order
 * of the definition. A field without a value gets an empty one, so a frontend never meets a missing key.
 */
final class ValidateStripContent
{
    use AsAction;

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidStripContent
     */
    public function handle(StripType $type, mixed $content): array
    {
        return $this->group($type->key, $type->fields, $content, '');
    }

    /**
     * The values of a group of fields: the strip itself, or one item of a list.
     *
     * @param  list<Field>  $fields
     * @return array<string, mixed>
     */
    private function group(string $type, array $fields, mixed $content, string $path): array
    {
        if (! is_array($content) || ($content !== [] && array_is_list($content))) {
            throw InvalidStripContent::because($type, $path, 'expected an object with the fields of the strip');
        }

        $names = array_map(fn (Field $field): string => $field->name, $fields);

        foreach (array_keys($content) as $name) {
            if (! in_array($name, $names, true)) {
                throw InvalidStripContent::because($type, $this->path($path, (string) $name), 'this field is not in the definition');
            }
        }

        $values = [];

        foreach ($fields as $field) {
            $values[$field->name] = $this->value($type, $field, $content[$field->name] ?? null, $this->path($path, $field->name));
        }

        return $values;
    }

    private function value(string $type, Field $field, mixed $value, string $path): mixed
    {
        return match ($field->type) {
            FieldType::Text, FieldType::Textarea => $this->text($type, $value, $path),
            FieldType::Image => $this->pair($type, $value, $path, ['src', 'alt']),
            FieldType::Link => $this->pair($type, $value, $path, ['label', 'url']),
            FieldType::List => $this->items($type, $field, $value, $path),
        };
    }

    private function text(string $type, mixed $value, string $path): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            throw InvalidStripContent::because($type, $path, 'expected text');
        }

        return $value;
    }

    /**
     * An image ({src, alt}) or a link ({label, url}): two texts, of which a missing one stays empty.
     *
     * @param  list<string>  $keys
     * @return array<string, string>|null
     */
    private function pair(string $type, mixed $value, string $path, array $keys): ?array
    {
        if ($value === null || $value === []) {
            return null;
        }

        $expected = 'expected {'.implode(', ', $keys).'}';

        if (! is_array($value) || array_diff(array_keys($value), $keys) !== []) {
            throw InvalidStripContent::because($type, $path, $expected);
        }

        $pair = [];

        foreach ($keys as $key) {
            $text = $value[$key] ?? '';

            if (! is_string($text)) {
                throw InvalidStripContent::because($type, $path, $expected);
            }

            $pair[$key] = $text;
        }

        return $pair;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $type, Field $field, mixed $value, string $path): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidStripContent::because($type, $path, 'expected a list of items');
        }

        $items = [];

        foreach ($value as $index => $item) {
            $items[] = $this->group($type, $field->fields, $item, $this->path($path, (string) $index));
        }

        return $items;
    }

    private function path(string $parent, string $name): string
    {
        return $parent === '' ? $name : "{$parent}.{$name}";
    }
}
