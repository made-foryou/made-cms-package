<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageHistory;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Data\VersionChanges;
use Made\CmsPackage\Content\Data\VersionSummary;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;
use Made\CmsPackage\Content\Strips\StripTypes;

final class ListPageVersions
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * The versions the page keeps, the newest first: the newest of a published page is the one visitors see. The
     * strips each version changed are named as the structure names them, from the catalogue as it is now; a removed
     * strip by the values it had when it went.
     */
    public function handle(Page $page): PageHistory
    {
        $live = $page->status === PageStatus::Published;
        $versions = [];

        foreach (PageVersion::query()->ofPage($page)->newestFirst()->get() as $version) {
            $strips = PageStrips::fromArray($version->strips);
            $changes = VersionChanges::fromArray($version->changes);

            $versions[] = new VersionSummary(
                number: $version->number,
                publishedAt: $version->published_at,
                publishedBy: $version->published_by,
                live: $live && $versions === [],
                first: $changes->first,
                added: $this->names($strips, $changes->added),
                changed: $this->names($strips, $changes->changed),
                moved: $this->names($strips, $changes->moved),
                removed: array_map($this->name(...), $changes->removed),
            );
        }

        return new PageHistory($versions, $page->draft_strips !== null);
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function names(PageStrips $strips, array $ids): array
    {
        $names = [];

        foreach ($ids as $id) {
            $strip = $strips->find($id);

            if ($strip !== null) {
                $names[] = $this->name($strip);
            }
        }

        return $names;
    }

    private function name(Strip $strip): string
    {
        if (! $this->types->has($strip->type)) {
            return $strip->type;
        }

        $type = $this->types->find($strip->type);

        return $type->summary($strip->values) ?? $type->label();
    }
}
