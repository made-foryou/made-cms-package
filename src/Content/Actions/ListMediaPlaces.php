<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaPlace;
use Made\CmsPackage\Content\Data\MediaPlaces;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

/**
 * @phpstan-type PageStripsByVersion array{positions: array<string, int>, draft: array<string, array<string, mixed>>, published: array<string, array<string, mixed>>}
 */
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
     * the strips of its working copy and of the fields and items in a strip. A place is named from the catalogue
     * ("Hero › Achtergrondfoto", "Fotorij › Foto 3 › Afbeelding", the number of an item from the version the place
     * is in, the working copy first), and by its keys ("hero › foto") when the type left the catalogue or no longer
     * has the field. A deleted page that can still be restored counts, marked as deleted.
     *
     * It reads the places the file was loaded with (MediaBuilder::withUsage()), else it loads them once.
     */
    public function handle(Media $media): MediaPlaces
    {
        $media->loadMissing('usages.page');

        $places = [];
        $pages = [];

        foreach ($media->usages as $usage) {
            $page = $usage->page;

            if ($page === null) {
                continue;
            }

            $pages[$page->id] ??= self::strips($page);
            $key = "{$usage->page_id}\0{$usage->strip_id}\0{$usage->field}";

            $places[$key] ??= ['usage' => $usage, 'page' => $page, 'versions' => []];
            $places[$key]['versions'][] = $usage->version;
        }

        $places = array_map(function (array $place) use ($pages): array {
            $strips = $pages[$place['page']->id];
            $stripId = $place['usage']->strip_id;
            $values = in_array(UsageVersion::Draft, $place['versions'], true)
                ? $strips['draft'][$stripId] ?? $strips['published'][$stripId] ?? null
                : $strips['published'][$stripId] ?? $strips['draft'][$stripId] ?? null;

            return [
                ...$place,
                'position' => $strips['positions'][$stripId] ?? PHP_INT_MAX,
                'order' => $this->order($place['usage'], $values),
                'where' => $this->where($place['usage'], $values),
            ];
        }, array_values($places));

        usort($places, static fn (array $a, array $b): int => strnatcasecmp($a['page']->title, $b['page']->title)
            ?: $a['page']->id <=> $b['page']->id
            ?: $a['position'] <=> $b['position']
            ?: strcmp($a['usage']->strip_id, $b['usage']->strip_id)
            ?: $a['order'] <=> $b['order']
            ?: strnatcmp($a['usage']->field, $b['usage']->field));

        return new MediaPlaces(array_map(static fn (array $place): MediaPlace => new MediaPlace(
            page: $place['page']->id,
            pageTitle: $place['page']->title,
            where: $place['where'],
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
     * The strips of the page: the place of every strip in the working copy, else in the published version (a strip
     * the draft removed) after them, and the values of every strip per version.
     *
     * @return PageStripsByVersion
     */
    private static function strips(Page $page): array
    {
        $strips = ['positions' => [], 'draft' => [], 'published' => []];

        foreach (PageStrips::fromArray($page->draft_strips ?? [])->strips as $strip) {
            $strips['draft'][$strip->id] = $strip->values;
        }

        foreach (PageStrips::fromArray($page->strips)->strips as $strip) {
            $strips['published'][$strip->id] = $strip->values;
        }

        $working = $page->draft_strips === null ? $strips['published'] : $strips['draft'];

        foreach ([...array_keys($working), ...array_keys($strips['published'])] as $id) {
            $strips['positions'][$id] ??= count($strips['positions']);
        }

        return $strips;
    }

    /**
     * The name of the strip and the labels of the field, or the keys when the catalogue cannot name the place.
     *
     * @param  array<string, mixed>|null  $values  the stored values of the strip in the version the place is in
     */
    private function where(MediaUsage $usage, ?array $values): string
    {
        if ($values !== null && $this->types->has($usage->strip_type)) {
            $type = $this->types->find($usage->strip_type);
            $labels = $type->placeLabels($usage->field, $values);

            if ($labels !== null) {
                return implode(self::SEPARATOR, [$type->label(), ...$labels]);
            }
        }

        return $usage->strip_type.self::SEPARATOR.$usage->field;
    }

    /**
     * The place among the files of the strip, in the order of its fields and items; last when it cannot be read.
     *
     * @param  array<string, mixed>|null  $values
     */
    private function order(MediaUsage $usage, ?array $values): int
    {
        if ($values === null || ! $this->types->has($usage->strip_type)) {
            return PHP_INT_MAX;
        }

        $order = array_search($usage->field, array_keys($this->types->find($usage->strip_type)->places($values)), true);

        return $order === false ? PHP_INT_MAX : $order;
    }
}
