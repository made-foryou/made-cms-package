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
     *                         "Fotorij › Foto 3 › Afbeelding" in a list; the keys when the catalogue cannot name it
     * @param  string  $strip  the id of the strip
     * @param  string  $field  the path of the field in the strip: "foto", or "fotos.{id}.afbeelding" by the id of the item
     * @param  list<UsageVersion>  $versions  the versions of the page the place is in: the draft, the published version or both
     * @param  bool  $pageDeleted  the page is deleted and can still be restored
     * @param  bool  $pageHasDraft  the page has a draft, so a place only in its published version is one the draft took away
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
    ) {}

    public function in(UsageVersion $version): bool
    {
        return in_array($version, $this->versions, true);
    }
}
