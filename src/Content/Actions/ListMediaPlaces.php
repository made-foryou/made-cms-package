<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaPlace;
use Made\CmsPackage\Content\Data\MediaPlaces;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

final class ListMediaPlaces
{
    use AsAction;

    private const string SEPARATOR = ' › ';

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * The places a file of the media library is used, as the library shows them: a place in the draft and in the
     * published version of a page once, with both versions; the pages by title, the places of a page in the order of
     * the strips of its working copy. A place is named from the catalogue ("Hero › Achtergrondfoto",
     * "Fotorij › Foto 3 › Afbeelding"), and by its keys ("hero › foto") when the type left the catalogue or no longer
     * has the field. A deleted page that can still be restored counts, marked as deleted.
     *
     * It reads the places the file was loaded with (MediaBuilder::withUsage()), else it loads them once.
     */
    public function handle(Media $media): MediaPlaces
    {
        $media->loadMissing(['usages.page' => static function (BelongsTo $page): void {
            $page->withTrashed();
        }]);

        $places = [];
        $positions = [];

        foreach ($media->usages as $usage) {
            $page = $usage->page;

            if ($page === null) {
                continue;
            }

            $key = "{$usage->page_id}\0{$usage->strip_id}\0{$usage->field}";
            $positions[$page->id] ??= self::positions($page);

            $places[$key] ??= [
                'usage' => $usage,
                'page' => $page,
                'position' => $positions[$page->id][$usage->strip_id] ?? PHP_INT_MAX,
                'versions' => [],
            ];
            $places[$key]['versions'][] = $usage->version;
        }

        usort($places, static fn (array $a, array $b): int => strnatcasecmp($a['page']->title, $b['page']->title)
            ?: $a['page']->id <=> $b['page']->id
            ?: $a['position'] <=> $b['position']
            ?: strcmp($a['usage']->strip_id, $b['usage']->strip_id)
            ?: strnatcmp($a['usage']->field, $b['usage']->field));

        return new MediaPlaces(array_map(fn (array $place): MediaPlace => new MediaPlace(
            page: $place['page']->id,
            pageTitle: $place['page']->title,
            where: $this->where($place['usage']),
            strip: $place['usage']->strip_id,
            field: $place['usage']->field,
            versions: array_values(array_filter(
                UsageVersion::cases(),
                static fn (UsageVersion $version): bool => in_array($version, $place['versions'], true),
            )),
            pageDeleted: $place['page']->trashed(),
        ), $places));
    }

    /**
     * The place of every strip on the page: in the working copy, else in the published version (a strip the draft
     * removed) after them.
     *
     * @return array<string, int>
     */
    private static function positions(Page $page): array
    {
        $positions = [];
        $versions = $page->draft_strips === null ? [$page->strips] : [$page->draft_strips, $page->strips];

        foreach ($versions as $strips) {
            foreach (PageStrips::fromArray($strips)->strips as $strip) {
                $positions[$strip->id] ??= count($positions);
            }
        }

        return $positions;
    }

    private function where(MediaUsage $usage): string
    {
        if ($this->types->has($usage->strip_type)) {
            $type = $this->types->find($usage->strip_type);
            $labels = $type->placeLabels($usage->field);

            if ($labels !== null) {
                return implode(self::SEPARATOR, [$type->label(), ...$labels]);
            }
        }

        return $usage->strip_type.self::SEPARATOR.$usage->field;
    }
}
