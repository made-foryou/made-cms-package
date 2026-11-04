<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Illuminate\Support\Str;

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
            'default' => $this->default,
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
     * The value as it is stored, after validation.
     */
    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * The value as the site renders it, from the stored value: what a view or a front-end component gets.
     */
    public function render(mixed $value): mixed
    {
        return $value;
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
     * @return 'required'|'nullable'
     */
    protected function presence(): string
    {
        return $this->required ? 'required' : 'nullable';
    }
}
