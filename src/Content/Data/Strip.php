<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip on a page: an instance of a strip type with the values an editor filled in.
 *
 * The id stays the same for the life of the strip, also when it moves or is published.
 *
 * @phpstan-type StoredStrip array{id: string, type: string, visible: bool, values: array<string, mixed>}
 */
final readonly class Strip
{
    /**
     * @param  string  $type  the key of the strip type; it may no longer be in the catalogue
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public string $id,
        public string $type,
        public bool $visible,
        public array $values,
    ) {}

    /**
     * @param  array<mixed>  $stored  a strip as it is stored in cms_pages
     */
    public static function fromArray(array $stored): self
    {
        $values = $stored['values'] ?? [];

        /** @var array<string, mixed> $values */
        $values = is_array($values) ? $values : [];

        return new self(
            id: is_scalar($stored['id'] ?? null) ? (string) $stored['id'] : '',
            type: is_scalar($stored['type'] ?? null) ? (string) $stored['type'] : '',
            visible: (bool) ($stored['visible'] ?? true),
            values: $values,
        );
    }

    /**
     * @return StoredStrip
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'visible' => $this->visible,
            'values' => $this->values,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function withValues(array $values): self
    {
        return new self($this->id, $this->type, $this->visible, $values);
    }

    public function withVisibility(bool $visible): self
    {
        return new self($this->id, $this->type, $visible, $this->values);
    }
}
