<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

/**
 * Reads one strip type from its decoded JSON definition. Strict on purpose: a typo in a hand-edited file
 * is an error that names the field, never a field that silently disappears.
 */
final class ParseStripType
{
    use AsAction;

    private const string KEY = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    private const string NAME = '/^[a-z][a-z0-9_]*$/D';

    /**
     * @throws InvalidStripType
     */
    public function handle(string $key, mixed $definition): StripType
    {
        if (preg_match(self::KEY, $key) !== 1) {
            throw InvalidStripType::because($key, 'the key is not written in kebab-case, e.g. "hero" or "over-ons"');
        }

        if (! is_array($definition)) {
            throw InvalidStripType::because($key, 'the definition is not an object');
        }

        $this->rejectUnknownKeys($key, $definition, ['$schema', 'label', 'fields'], 'the definition');

        return new StripType(
            key: $key,
            label: $this->label($key, $definition, 'the definition'),
            fields: $this->fields($key, $definition['fields'] ?? null, ''),
        );
    }

    /**
     * The fields of the strip type, or of the list at $parent.
     *
     * @return list<Field>
     */
    private function fields(string $key, mixed $fields, string $parent): array
    {
        if (! is_array($fields) || ! array_is_list($fields)) {
            throw InvalidStripType::because($key, $parent === '' ? 'the definition needs a list of fields' : "field [{$parent}] needs a list of fields");
        }

        if ($parent !== '' && $fields === []) {
            throw InvalidStripType::because($key, "field [{$parent}] needs at least one field");
        }

        $parsed = [];

        foreach ($fields as $definition) {
            $field = $this->field($key, $definition, $parent);

            foreach ($parsed as $earlier) {
                if ($earlier->name === $field->name) {
                    throw InvalidStripType::because($key, 'field ['.$this->path($parent, $field->name).'] appears twice');
                }
            }

            $parsed[] = $field;
        }

        return $parsed;
    }

    private function field(string $key, mixed $field, string $parent): Field
    {
        $name = is_array($field) && is_string($field['name'] ?? null) ? $field['name'] : '';
        $path = $this->path($parent, $name);

        if (! is_array($field) || preg_match(self::NAME, $name) !== 1) {
            throw InvalidStripType::because($key, "every field needs a name in snake_case, found [{$path}]");
        }

        $where = "field [{$path}]";

        $this->rejectUnknownKeys($key, $field, ['name', 'type', 'label', 'fields'], $where);

        $type = is_string($field['type'] ?? null) ? FieldType::tryFrom($field['type']) : null;

        if ($type === null) {
            throw InvalidStripType::because($key, "{$where} has no known type (".implode(', ', array_column(FieldType::cases(), 'value')).')');
        }

        if ($type === FieldType::List && $parent !== '') {
            throw InvalidStripType::because($key, "{$where} is a list inside a list");
        }

        if ($type !== FieldType::List && array_key_exists('fields', $field)) {
            throw InvalidStripType::because($key, "{$where} only has fields when it is a list");
        }

        return new Field(
            name: $name,
            type: $type,
            label: $this->label($key, $field, $where),
            fields: $type === FieldType::List ? $this->fields($key, $field['fields'] ?? null, $path) : [],
        );
    }

    /**
     * @param  array<array-key, mixed>  $definition
     */
    private function label(string $key, array $definition, string $where): string
    {
        $label = $definition['label'] ?? null;

        if (! is_string($label) || trim($label) === '') {
            throw InvalidStripType::because($key, "{$where} needs a label");
        }

        return trim($label);
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @param  list<string>  $allowed
     */
    private function rejectUnknownKeys(string $key, array $definition, array $allowed, string $where): void
    {
        foreach (array_keys($definition) as $name) {
            if (! in_array($name, $allowed, true)) {
                throw InvalidStripType::because($key, "{$where} has an unknown key [{$name}]");
            }
        }
    }

    private function path(string $parent, string $name): string
    {
        return $parent === '' ? $name : "{$parent}.{$name}";
    }
}
