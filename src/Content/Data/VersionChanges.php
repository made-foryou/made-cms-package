<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What a publication changed compared to the strips that were live before it, as facts by strip id, the way a version
 * of a page stores them; the screen makes the sentence when it shows it.
 *
 * A removed strip is kept whole, so it can still be named once the versions before it are pruned.
 *
 * @phpstan-import-type StoredStrip from Strip
 *
 * @phpstan-type StoredChanges array{first: bool, added: list<string>, changed: list<string>, moved: list<string>, removed: list<StoredStrip>}
 */
final readonly class VersionChanges
{
    /**
     * @param  bool  $first  whether the page went live for the first time; the lists are empty then
     * @param  list<string>  $added  strips that were not live
     * @param  list<string>  $changed  strips with other values or another visibility
     * @param  list<string>  $moved  strips that only changed place
     * @param  list<Strip>  $removed  strips that were live and are no longer
     */
    public function __construct(
        public bool $first,
        public array $added = [],
        public array $changed = [],
        public array $moved = [],
        public array $removed = [],
    ) {}

    /**
     * @param  array<mixed>  $stored  the changes as a version stores them
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            first: ($stored['first'] ?? false) === true,
            added: self::ids($stored['added'] ?? null),
            changed: self::ids($stored['changed'] ?? null),
            moved: self::ids($stored['moved'] ?? null),
            removed: PageStrips::fromArray(is_array($stored['removed'] ?? null) ? $stored['removed'] : [])->strips,
        );
    }

    /**
     * @return StoredChanges
     */
    public function toArray(): array
    {
        return [
            'first' => $this->first,
            'added' => $this->added,
            'changed' => $this->changed,
            'moved' => $this->moved,
            'removed' => (new PageStrips($this->removed))->toArray(),
        ];
    }

    public function count(): int
    {
        return count($this->added) + count($this->changed) + count($this->moved) + count($this->removed);
    }

    /**
     * @return list<string>
     */
    private static function ids(mixed $stored): array
    {
        return is_array($stored) ? array_values(array_filter($stored, is_string(...))) : [];
    }
}
