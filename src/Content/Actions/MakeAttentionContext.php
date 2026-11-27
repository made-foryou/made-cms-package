<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Models\Page;

final class MakeAttentionContext
{
    use AsAction;

    /**
     * What the sources may know about the other pages, from pages already read with their addresses: it reads nothing
     * itself. Two pages share a title when Google shows both and their titles in Google (their own or the one they get
     * without) are the same on one line, whatever the capitals.
     *
     * @param  iterable<Page>  $pages
     */
    public function handle(iterable $pages): AttentionContext
    {
        $byTitle = [];

        foreach ($pages as $page) {
            $measure = MeasurePageMeta::run($page);

            if ($measure->shown()) {
                $byTitle[Str::lower(Str::squish($measure->googleTitle))][] = $page;
            }
        }

        $shared = [];

        foreach ($byTitle as $sharing) {
            if (count($sharing) < 2) {
                continue;
            }

            usort($sharing, static fn (Page $a, Page $b): int => strnatcasecmp($a->title, $b->title) ?: $a->id <=> $b->id);

            foreach ($sharing as $page) {
                $shared[$page->id] = array_values(array_map(
                    static fn (Page $other): string => $other->title,
                    array_filter($sharing, static fn (Page $other): bool => $other->id !== $page->id),
                ));
            }
        }

        return new AttentionContext($shared);
    }
}
