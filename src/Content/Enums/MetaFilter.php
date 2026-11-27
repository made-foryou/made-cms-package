<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

use Made\CmsPackage\Content\Data\MetaRow;

/**
 * A filter of the Meta-overzicht. Missing, long and in order follow the standing of a page Google shows, so they
 * split those pages without overlap; all is every page, also one Google does not show.
 */
enum MetaFilter: string
{
    case Missing = 'missing';
    case Long = 'long';
    case Ok = 'ok';
    case All = 'all';

    public function matches(MetaRow $row): bool
    {
        return $this === self::All || $row->measure->standing() === $this->standing();
    }

    /**
     * Whether the screen offers the filter also when it counts nothing: in order and all always, so the screen of a
     * site where all is well still has its list.
     */
    public function alwaysOffered(): bool
    {
        return $this === self::Ok || $this === self::All;
    }

    private function standing(): ?MetaStanding
    {
        return match ($this) {
            self::Missing => MetaStanding::Missing,
            self::Long => MetaStanding::Long,
            self::Ok => MetaStanding::Ok,
            self::All => null,
        };
    }
}
