<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The strips of a page in their order. Every change returns a new list.
 *
 * The list only rearranges; whether a strip exists or a position is allowed
 * is decided by the Actions that use it.
 *
 * @phpstan-import-type StoredStrip from Strip
 */
final readonly class PageStrips
{
    /**
     * @param  list<Strip>  $strips
     */
    public function __construct(
        public array $strips,
    ) {}

    /**
     * @param  array<mixed>  $stored  the strips as they are stored in cms_pages
     */
    public static function fromArray(array $stored): self
    {
        $strips = [];

        foreach ($stored as $strip) {
            if (is_array($strip)) {
                $strips[] = Strip::fromArray($strip);
            }
        }

        return new self($strips);
    }

    /**
     * @return list<StoredStrip>
     */
    public function toArray(): array
    {
        return array_map(static fn (Strip $strip): array => $strip->toArray(), $this->strips);
    }

    public function count(): int
    {
        return count($this->strips);
    }

    public function find(string $id): ?Strip
    {
        $position = $this->positionOf($id);

        return $position === null ? null : $this->strips[$position];
    }

    public function positionOf(string $id): ?int
    {
        foreach ($this->strips as $position => $strip) {
            if ($strip->id === $id) {
                return $position;
            }
        }

        return null;
    }

    public function insert(Strip $strip, int $position): self
    {
        $strips = $this->strips;

        array_splice($strips, $position, 0, [$strip]);

        return new self($strips);
    }

    public function remove(string $id): self
    {
        return new self(array_values(array_filter(
            $this->strips,
            static fn (Strip $strip): bool => $strip->id !== $id,
        )));
    }

    public function move(string $id, int $position): self
    {
        $strip = $this->find($id);

        if ($strip === null) {
            return $this;
        }

        return $this->remove($id)->insert($strip, $position);
    }

    public function replace(Strip $replacement): self
    {
        return new self(array_map(
            static fn (Strip $strip): Strip => $strip->id === $replacement->id ? $replacement : $strip,
            $this->strips,
        ));
    }
}
