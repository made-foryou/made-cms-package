<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;

/**
 * A kind of strip editors can place on a page, defined by the site in app/Cms/Strips.
 *
 * The key is stored with every strip, so it never changes once content uses it,
 * also not when the class is renamed.
 */
abstract class StripType
{
    private const int SUMMARY_LENGTH = 120;

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
     * The messages for the validation of its values, in the language of the CMS, for every rule its fields use.
     * They take the place of the host's validation messages, so an editor never gets them in another language.
     *
     * @return array<string, string|array<string, string>>
     */
    final public function messages(): array
    {
        $message = static fn (string $key): string => (string) __('made-cms-package::strips.validation.'.$key);

        return [
            'required' => $message('required'),
            'required_with' => $message('required'),
            'string' => $message('string'),
            'array' => $message('array'),
            'list' => $message('list'),
            'in' => $message('in'),
            'uuid' => $message('uuid'),
            'max' => ['string' => $message('max_string'), 'array' => $message('max_array')],
            'min' => ['string' => $message('min_string'), 'array' => $message('min_array')],
        ];
    }

    /**
     * The strip type as the side panel builds it: its key, name, explanation and its fields in order.
     *
     * @return array{key: string, label: string, description: string|null, fields: list<array<string, mixed>>}
     */
    final public function describe(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'description' => $this->description(),
            'fields' => array_map(static fn (Field $field): array => $field->describe(), $this->fields()),
        ];
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
     * The values of an editor who does not choose the level of a heading, with every heading at its stored level
     * (also in a list, by the id of the item) and a new one at its default level. Only the fields it gets change.
     *
     * @param  array<string, mixed>  $stored  stored values in their current form
     * @param  array<string, mixed>  $values  the values to store
     * @return array<string, mixed>
     */
    final public function keepLevels(array $stored, array $values): array
    {
        foreach ($this->fields() as $field) {
            $key = $field->getKey();

            if (array_key_exists($key, $values)) {
                $values[$key] = $field->keepLevel($stored[$key] ?? null, $values[$key]);
            }
        }

        return $values;
    }

    /**
     * The fields that are a heading, by the path the edit shell knows a field by: "kop", or "kaarten.*.titel" for a
     * heading in every item of a list, with the label of that list. The overview of the headings uses it to find a
     * heading at "Geen kop" (a span) on the site and to tell a heading whose level an editor chooses from one the
     * view prints.
     *
     * @return list<array{path: string, list: string|null}>
     */
    final public function headings(): array
    {
        $headings = [];

        foreach ($this->fields() as $field) {
            if ($field instanceof Heading) {
                $headings[] = ['path' => $field->getKey(), 'list' => null];
            }

            if ($field instanceof Items) {
                foreach ($field->getFields() as $sub) {
                    if ($sub instanceof Heading) {
                        $headings[] = ['path' => $field->getKey().'.*.'.$sub->getKey(), 'list' => $field->getLabel()];
                    }
                }
            }
        }

        return $headings;
    }

    /**
     * The levels of the headings a visitor gets from these values, in the order of its fields and of the items of a
     * list: as render() gives them, a missing field from its default, a heading without a text left out and "span" for
     * one at "Geen kop". Where headings() describes the definition for the edit shell, this reads the content; the
     * extra data of with() is no part of it.
     *
     * @param  array<string, mixed>  $values  stored values
     * @return list<string>
     */
    final public function headingLevels(array $values): array
    {
        $levels = [];

        foreach ($this->fields() as $field) {
            $key = $field->getKey();
            $levels = [...$levels, ...$field->headingLevels(array_key_exists($key, $values) ? $values[$key] : $field->getDefault())];
        }

        return $levels;
    }

    /**
     * The running text of the strip (Field::prose()), a text per field that holds it, in the order of its fields, a
     * missing field from its default. The items of a list are left out: cards are no running text of a page.
     *
     * @param  array<string, mixed>  $values  stored values
     * @return list<string>
     */
    final public function prose(array $values): array
    {
        $prose = [];

        foreach ($this->fields() as $field) {
            $key = $field->getKey();
            $text = $field->prose(array_key_exists($key, $values) ? $values[$key] : $field->getDefault());

            if ($text !== null) {
                $prose[] = $text;
            }
        }

        return $prose;
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
     * The strip in a few words for the lists of the CMS: the first field with text in the order of the definition,
     * else the items of the first list that has text in it; null when the strip holds no text yet.
     *
     * @param  array<string, mixed>  $values  stored values; a missing field counts as its default
     */
    final public function summary(array $values): ?string
    {
        $fields = $this->fields();
        $lists = array_filter($fields, static fn (Field $field): bool => $field instanceof Items);
        $others = array_filter($fields, static fn (Field $field): bool => ! $field instanceof Items);

        foreach ([...$others, ...$lists] as $field) {
            $key = $field->getKey();
            $summary = $field->summary(array_key_exists($key, $values) ? $values[$key] : $field->getDefault());

            if ($summary !== null) {
                return Str::limit($summary, self::SUMMARY_LENGTH);
            }
        }

        return null;
    }

    /**
     * The ids of the files of the media library the strip uses, in the order of its fields, each once: an image, also
     * in an item of a list. Values of fields the type no longer has are left out.
     *
     * @param  array<string, mixed>  $values  stored values
     * @return list<int>
     */
    final public function media(array $values): array
    {
        return array_values(array_unique(array_values($this->places($values))));
    }

    /**
     * The files of the media library the strip uses by the path of their place, in the order of its fields: "foto",
     * or "kaarten.{id}.foto" for an image in an item of a list, by the id of the item. A file in two places is there
     * twice. Values of fields the type no longer has are left out.
     *
     * @param  array<string, mixed>  $values  stored values
     * @return array<string, int>
     */
    final public function places(array $values): array
    {
        $places = [];

        foreach ($this->fields() as $field) {
            $key = $field->getKey();
            $places = [...$places, ...$field->places($values[$key] ?? null, $key)];
        }

        return $places;
    }

    /**
     * The labels that name a place of the strip, without the name of the strip: ["Achtergrondfoto"], or
     * ["Foto 3", "Afbeelding"] for a field in the third item of a list in these values. Null when the path is no place
     * of a field the type has (any more), or of an item the values hold.
     *
     * @param  array<string, mixed>  $values  stored values of the strip, where an item of a list gets its number
     * @return list<string>|null
     */
    final public function placeLabels(string $path, array $values): ?array
    {
        $segments = explode('.', $path);
        $key = array_shift($segments);

        foreach ($this->fields() as $field) {
            if ($field->getKey() === $key) {
                return $field->placeLabels($segments, $values[$key] ?? null);
            }
        }

        return null;
    }

    /**
     * The values as the site renders them: every field in the order of the definition,
     * a missing one from its default, links as urls, images from the files of the library,
     * and the extra data of with() on top.
     *
     * @param  array<string, mixed>  $values  stored values
     * @param  MediaFiles|null  $media  the files the strip uses (FindMedia); without them an image renders as null
     * @return array<string, mixed>
     */
    final public function render(array $values, ?MediaFiles $media = null): array
    {
        $rendered = [];

        foreach ($this->fields() as $field) {
            $key = $field->getKey();
            $rendered[$key] = $field->render(array_key_exists($key, $values) ? $values[$key] : $field->getDefault(), $media);
        }

        return [...$rendered, ...$this->with($rendered)];
    }
}
