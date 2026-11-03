<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Strips\Fields\Field;

/**
 * A kind of strip editors can place on a page, defined by the site in app/Cms/Strips.
 *
 * The key is stored with every strip, so it never changes once content uses it,
 * also not when the class is renamed.
 */
abstract class StripType
{
    /**
     * The fixed identifier: lowercase letters, digits and dashes, e.g. "hero".
     */
    abstract public function key(): string;

    /**
     * The fields an editor fills in, in the order of the side panel.
     *
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * The name editors see in the catalogue.
     */
    public function label(): string
    {
        return Str::headline(class_basename(static::class));
    }

    /**
     * A short explanation for the catalogue, if the name is not enough.
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * The Blade view that renders the strip; null means the convention "strips.{key}".
     */
    public function view(): ?string
    {
        return null;
    }

    /**
     * Extra data from the site to render the strip with, such as the latest news.
     * Its keys land next to the fields and override a field with the same name.
     *
     * @param  array<string, mixed>  $values  the rendered values of the strip: every field, links as urls
     * @return array<string, mixed>
     */
    public function with(array $values): array
    {
        return [];
    }

    /**
     * The Laravel validation rules for the values of a strip, keyed by path.
     *
     * @return array<string, list<mixed>>
     */
    final public function rules(): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $rules = [...$rules, ...$field->rules($field->getKey())];
        }

        return $rules;
    }

    /**
     * The field labels for the validation messages, keyed by path.
     *
     * @return array<string, string>
     */
    final public function attributes(): array
    {
        $attributes = [];

        foreach ($this->fields() as $field) {
            $attributes = [...$attributes, ...$field->attributes($field->getKey())];
        }

        return $attributes;
    }

    /**
     * The values a new strip starts with.
     *
     * @return array<string, mixed>
     */
    final public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields() as $field) {
            $defaults[$field->getKey()] = $field->getDefault();
        }

        return $defaults;
    }

    /**
     * The values to validate when an editor changes a strip: the stored values with the new ones on top.
     * A field it gets replaces the stored value (a list merges its items by id), a field it does not get
     * keeps its stored value. Keys the type does not know are left for the validation to drop.
     *
     * @param  array<string, mixed>  $stored  stored values
     * @param  array<string, mixed>  $values  new values, any number of fields
     * @return array<string, mixed>
     */
    final public function merge(array $stored, array $values): array
    {
        foreach ($this->fields() as $field) {
            $key = $field->getKey();

            if (array_key_exists($key, $values)) {
                $values[$key] = $field->merge($stored[$key] ?? null, $values[$key]);
            }
        }

        return [...$stored, ...$values];
    }

    /**
     * The values as they are stored: rich text cleaned, links in their stored form.
     *
     * @param  array<string, mixed>  $values  validated values
     * @return array<string, mixed>
     */
    final public function normalize(array $values): array
    {
        foreach ($this->fields() as $field) {
            if (array_key_exists($field->getKey(), $values)) {
                $values[$field->getKey()] = $field->normalize($values[$field->getKey()]);
            }
        }

        return $values;
    }

    /**
     * The values as the site renders them: every field in the order of the definition,
     * a missing one from its default, links as urls, and the extra data of with() on top.
     *
     * @param  array<string, mixed>  $values  stored values
     * @return array<string, mixed>
     */
    final public function render(array $values): array
    {
        $rendered = [];

        foreach ($this->fields() as $field) {
            $key = $field->getKey();
            $rendered[$key] = $field->render(array_key_exists($key, $values) ? $values[$key] : $field->getDefault());
        }

        return [...$rendered, ...$this->with($rendered)];
    }
}
