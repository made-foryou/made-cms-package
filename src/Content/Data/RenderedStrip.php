<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip as the site renders it: its id, the key of its strip type and its rendered values.
 *
 * A Blade view reads the values as properties ($strip->kop); an unknown name reads as null,
 * like a missing key in a JavaScript object.
 */
final readonly class RenderedStrip
{
    /**
     * @param  array<string, mixed>  $values  every field of the strip type rendered, with the extra data of with()
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $values,
    ) {}

    /**
     * The id and type first, then the values; a value named "id" or "type" never replaces them.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type] + $this->values;
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }
}
