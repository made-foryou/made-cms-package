<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Data\VersionChanges;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

final class RecordPageVersion
{
    use AsAction;

    /**
     * Keeps the strips that go live as the next version of the page, with what they change compared to the strips that
     * are live now. Only PutDraftLive runs it, inside the transaction of ChangeDraft with the row of the page locked and
     * before it writes the strips, so the numbers of a page follow each other and the page still holds what was live.
     */
    public function handle(Page $page, PageStrips $strips, CarbonImmutable $at, ?int $editorId): PageVersion
    {
        return PageVersion::query()->create([
            'page_id' => $page->id,
            'number' => PageVersion::query()->ofPage($page)->highestNumber() + 1,
            'strips' => $strips->toArray(),
            'changes' => $this->changes($page)->toArray(),
            'published_by' => $editorId,
            'published_at' => $at,
        ]);
    }

    private function changes(Page $page): VersionChanges
    {
        $draft = CompareDraft::run($page);

        if (! $draft->live) {
            return new VersionChanges(first: true);
        }

        $live = PageStrips::fromArray($page->strips);

        return new VersionChanges(
            first: false,
            added: $draft->added,
            changed: $draft->changed,
            moved: $draft->moved,
            removed: array_values(array_filter(
                array_map(static fn (string $id): ?Strip => $live->find($id), $draft->removed),
                static fn (?Strip $strip): bool => $strip !== null,
            )),
        );
    }
}
