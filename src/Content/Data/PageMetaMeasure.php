<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MetaStanding;
use Made\CmsPackage\Content\Enums\NotShownInGoogle;

/**
 * How a page does in Google (`MeasurePageMeta`): the title Google shows, how it and the description measure up, and
 * why Google does not show the page, if it does not. The attention points of the meta and the Meta-overzicht read
 * this one measure.
 */
final readonly class PageMetaMeasure
{
    /**
     * @param  string  $googleTitle  the title of the page itself, else the one it gets without (`MakeGoogleTitle`)
     */
    public function __construct(
        public string $googleTitle,
        public MetaLength $title,
        public MetaLength $description,
        public ?NotShownInGoogle $notShown,
    ) {}

    public function shown(): bool
    {
        return $this->notShown === null;
    }

    /**
     * Null for a page Google does not show: its title and description matter only once it does.
     */
    public function standing(): ?MetaStanding
    {
        return match (true) {
            ! $this->shown() => null,
            $this->description->missing => MetaStanding::Missing,
            $this->title->tooLong || $this->description->tooLong => MetaStanding::Long,
            default => MetaStanding::Ok,
        };
    }

    /**
     * Whether the page is a point to work through: Google shows it without a description or cuts something off.
     */
    public function needsWork(): bool
    {
        return in_array($this->standing(), [MetaStanding::Missing, MetaStanding::Long], true);
    }
}
