<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

/**
 * @phpstan-type Place array{media_id: int, page_id: int, version: string, strip_id: string, strip_type: string, field: string}
 */
final class SyncMediaUsage
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $types,
    ) {}

    /**
     * Brings the rows of a page in cms_media_usages in step with its strips: a row for every place a file of the
     * library is in the published strips, and in the draft while there is one. A hidden strip counts, a deleted page
     * too. The fields say which values are files (StripType::places()); the id of a file that is not in the library
     * gets no row. A strip whose type left the catalogue cannot be read, so it keeps the rows it has while it is on
     * the page, and its copy in the draft takes the rows of the published strip. Only what changed is written.
     *
     * The caller holds the row of the page locked (ChangeDraft, RebuildMediaUsage), so two changes never write the
     * rows of a page at the same time.
     */
    public function handle(Page $page): void
    {
        $stored = MediaUsage::query()->onPage($page)->get();

        $wanted = $this->places($page, UsageVersion::Published, PageStrips::fromArray($page->strips), $stored);

        if ($page->draft_strips !== null) {
            $wanted = [...$wanted, ...$this->places($page, UsageVersion::Draft, PageStrips::fromArray($page->draft_strips), $stored)];
        }

        $wanted = $this->inLibrary($wanted);
        $stale = [];

        foreach ($stored as $usage) {
            $key = self::key($usage->version->value, $usage->strip_id, $usage->field);
            $place = $wanted[$key] ?? null;

            if ($place !== null && $place['media_id'] === $usage->media_id && $place['strip_type'] === $usage->strip_type) {
                unset($wanted[$key]);

                continue;
            }

            $stale[] = $usage->id;
        }

        if ($stale !== []) {
            MediaUsage::query()->whereKey($stale)->delete();
        }

        if ($wanted !== []) {
            MediaUsage::query()->insert(array_values($wanted));
        }
    }

    /**
     * @param  Collection<int, MediaUsage>  $stored
     * @return array<string, Place>
     */
    private function places(Page $page, UsageVersion $version, PageStrips $strips, Collection $stored): array
    {
        $places = [];

        foreach ($strips->strips as $strip) {
            if ($this->types->has($strip->type)) {
                $files = $this->types->find($strip->type)->places($strip->values);
            } else {
                $files = $this->unreadable($stored, $version, $strip->id, $strip->type);
            }

            foreach ($files as $field => $media) {
                $places[self::key($version->value, $strip->id, $field)] = [
                    'media_id' => $media,
                    'page_id' => $page->id,
                    'version' => $version->value,
                    'strip_id' => $strip->id,
                    'strip_type' => $strip->type,
                    'field' => $field,
                ];
            }
        }

        return $places;
    }

    /**
     * The places a strip whose type left the catalogue has in this version, else those of the other version: such a
     * strip cannot change, so its copy in the draft holds what the published strip holds.
     *
     * @param  Collection<int, MediaUsage>  $stored
     * @return array<string, int>
     */
    private function unreadable(Collection $stored, UsageVersion $version, string $stripId, string $type): array
    {
        $rows = $stored->filter(static fn (MediaUsage $usage): bool => $usage->strip_id === $stripId && $usage->strip_type === $type);
        $own = $rows->filter(static fn (MediaUsage $usage): bool => $usage->version === $version);
        $files = [];

        foreach ($own->isNotEmpty() ? $own : $rows as $usage) {
            $files[$usage->field] = $usage->media_id;
        }

        return $files;
    }

    /**
     * The places whose file is in the library.
     *
     * @param  array<string, Place>  $places
     * @return array<string, Place>
     */
    private function inLibrary(array $places): array
    {
        $ids = array_values(array_unique(array_column($places, 'media_id')));

        if ($ids === []) {
            return [];
        }

        $known = array_flip(Media::query()->whereKey($ids)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        return array_filter($places, static fn (array $place): bool => isset($known[$place['media_id']]));
    }

    private static function key(string $version, string $stripId, string $field): string
    {
        return "{$version}\0{$stripId}\0{$field}";
    }
}
