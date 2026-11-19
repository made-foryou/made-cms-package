<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\UsageVersion;

/**
 * One place a file of the media library is used, as the library shows it: on which page and where on it.
 */
final readonly class MediaPlace
{
    /**
     * @param  int  $page  the id of the page
     * @param  string  $where  the name of the strip and the labels of the field: "Hero › Achtergrondfoto", or
     *                         "Fotorij › Foto 3 › Afbeelding" in a list; the keys when the catalogue cannot name it.
     *                         A place of the page itself by its name: "Deelafbeelding"
     * @param  string  $strip  the id of the strip; empty for a place of the page itself
     * @param  string  $field  the path of the field in the strip: "foto", or "fotos.{id}.afbeelding" by the id of the
     *                         item; the value of its PagePlace for a place of the page itself
     * @param  list<UsageVersion>  $versions  the versions of the page the place is in: the draft, the published version
     *                                        or both, or Page for a place of the page itself, outside its draft
     * @param  bool  $pageDeleted  the page is deleted and can still be restored
     * @param  bool  $pageHasDraft  the page has a draft, so a place only in its published version is one the draft took away
     * @param  bool  $pagePublished  the page is published, so a place of the page itself is on the site
     */
    public function __construct(
        public int $page,
        public string $pageTitle,
        public string $where,
        public string $strip,
        public string $field,
        public array $versions,
        public bool $pageDeleted,
        public bool $pageHasDraft = false,
        public bool $pagePublished = false,
    ) {}

    public function in(UsageVersion $version): bool
    {
        return in_array($version, $this->versions, true);
    }

    /**
     * A place of the page itself (its share image), outside its strips and its draft.
     */
    public function ofPage(): bool
    {
        return $this->in(UsageVersion::Page);
    }

    /**
     * Whether visitors get the file at this place now: the published version of a page that is not in the bin, or a
     * place of the page itself once the page is published.
     */
    public function shownOnSite(): bool
    {
        if ($this->pageDeleted) {
            return false;
        }

        return $this->in(UsageVersion::Published) || ($this->ofPage() && $this->pagePublished);
    }
}
