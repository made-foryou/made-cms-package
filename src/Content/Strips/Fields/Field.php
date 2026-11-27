<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Data\MediaFiles;

/**
 * One field of a strip type. Fields are immutable: every setter returns a changed copy,
 * so a definition can never be altered by code that only reads it.
 */
abstract class Field
{
    protected ?string $label = null;

    protected ?string $hint = null;

    protected bool $required = false;

    protected mixed $default = null;

    final protected function __construct(
        protected readonly string $key,
    ) {}

    public function label(string $label): static
    {
        $field = clone $this;
        $field->label = $label;

        return $field;
    }

    public function hint(string $hint): static
    {
        $field = clone $this;
        $field->hint = $hint;

        return $field;
    }

    public function required(bool $required = true): static
    {
        $field = clone $this;
        $field->required = $required;

        return $field;
    }

    public function default(mixed $value): static
    {
        $field = clone $this;
        $field->default = $value;

        return $field;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * The label an editor sees; without one, the key reads as a sentence ("gaat_naar" becomes "Gaat naar").
     */
    public function getLabel(): string
    {
        return $this->label ?? Str::ucfirst(str_replace('_', ' ', $this->key));
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * The kind of field, so the side panel knows which control to show: "text", "items", …
     */
    abstract public function type(): string;

    /**
     * The field as the side panel builds it: its key, type, label, hint, whether it is required and its default,
     * with what the type adds (the options of a choice, the fields of an item, …).
     *
     * @return array<string, mixed>
     */
    final public function describe(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type(),
            'label' => $this->getLabel(),
            'hint' => $this->hint,
            'required' => $this->required,
            'default' => $this->getDefault(),
            ...$this->details(),
        ];
    }

    /**
     * The Laravel validation rules for this field at the given path, keyed by path.
     *
     * @return array<string, list<mixed>>
     */
    abstract public function rules(string $path): array;

    /**
     * The labels for the validation messages, keyed by path.
     *
     * @return array<string, string>
     */
    public function attributes(string $path): array
    {
        return [$path => $this->getLabel()];
    }

    /**
     * The value to validate when an editor changes this field: by default the new value replaces the stored one.
     *
     * @param  mixed  $stored  the stored value, null when there is none
     * @param  mixed  $value  the value the editor sends
     */
    public function merge(mixed $stored, mixed $value): mixed
    {
        return $value;
    }

    /**
     * The value an editor who does not choose the level of a heading sends, with the levels as stored: only a heading
     * has a level (also in a list), every other field gives the value back.
     *
     * @param  mixed  $stored  the stored value in its current form, null when there is none
     * @param  mixed  $value  the value the editor sends
     */
    public function keepLevel(mixed $stored, mixed $value): mixed
    {
        return $value;
    }

    /**
     * The value as it is stored, after validation.
     */
    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * The value as the site renders it, from the stored value: what a view or a front-end component gets.
     *
     * @param  MediaFiles|null  $media  the files of the library the strip uses (FindMedia); an image renders as null without them
     */
    public function render(mixed $value, ?MediaFiles $media = null): mixed
    {
        return $value;
    }

    /**
     * The ids of the files of the media library a stored value holds, each once: an image its file, a list the files
     * of its items. Fields without a file hold none.
     *
     * @return list<int>
     */
    final public function media(mixed $value): array
    {
        return array_values(array_unique(array_values($this->places($value, $this->key))));
    }

    /**
     * The files of the media library a stored value holds, by the path of their place: an image its file at the path
     * it gets, a list the files of its items by the id of the item ("kaarten.{id}.foto"), so a place stays the same
     * when the item moves. Fields without a file hold none. It is the one place that says which values are files; the
     * usage of the media library is built from it.
     *
     * @param  string  $path  the path of this field in the strip
     * @return array<string, int>
     */
    public function places(mixed $value, string $path): array
    {
        return [];
    }

    /**
     * The levels of the headings a visitor gets from a stored value, as render() gives them: a heading its level ("h1"
     * to "h6", or "span" at "Geen kop"), none without a text; a list those of its items in order. Fields that are no
     * heading give none.
     *
     * @return list<string>
     */
    public function headingLevels(mixed $value): array
    {
        return [];
    }

    /**
     * The labels that name a place in this field, from the rest of its path after its key: the label of the field
     * itself without a rest. A list names an item by its label and its number in the stored value. Null when the rest
     * is no place in it.
     *
     * @param  list<string>  $rest
     * @param  mixed  $value  the stored value of the field, where a list finds the number of an item
     * @return list<string>|null
     */
    public function placeLabels(array $rest, mixed $value): ?array
    {
        return $rest === [] ? [$this->getLabel()] : null;
    }

    /**
     * The value as a short text for the lists of the CMS (the opbouw of a page, what is on a page), or null when the
     * field holds no text to show there. Only fields with text say something; a link or a choice stays silent.
     */
    public function summary(mixed $value): ?string
    {
        return null;
    }

    /**
     * The running text of a stored value as plain text, a paragraph per line, or null when the field holds none: the
     * text a description in Google can be taken from (SuggestPageDescription). Only a long text and a rich text hold
     * running text; a short text, a heading or a list does not.
     */
    public function prose(mixed $value): ?string
    {
        return null;
    }

    /**
     * What a type adds to its description.
     *
     * @return array<string, mixed>
     */
    protected function details(): array
    {
        return [];
    }

    /**
     * The lines of a text with text in them, each on one line without the spaces around it, joined by line breaks; null
     * when no line holds text.
     */
    protected static function paragraphs(string $text): ?string
    {
        $lines = array_values(array_filter(
            array_map(self::line(...), preg_split('/\R/u', $text) ?: []),
            static fn (?string $line): bool => $line !== null,
        ));

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * A text on one line without the spaces around it, or null when nothing is left.
     */
    protected static function line(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $line = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $line === '' ? null : $line;
    }

    /**
     * @return 'required'|'nullable'
     */
    protected function presence(): string
    {
        return $this->required ? 'required' : 'nullable';
    }
}
