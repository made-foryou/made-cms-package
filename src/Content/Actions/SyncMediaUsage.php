<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Enums\PagePlace;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\MediaChoice;
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
     * Brings the rows of a page in cms_media_usages in step with its strips and itself: a row for every place a file
     * of the library is in the published strips, and in the draft while there is one, and for every place of the page
     * itself (its share image, version Page, outside the draft). A hidden strip counts, a deleted page too. The fields say which values are files (StripType::places()); the id of a file that is not in the library
     * gets no row. The catalogue no longer says which values of a strip whose type left it are files, so its rows in
     * either version say it: every such place holds the file its values in this version hold there, so publishing
     * the draft of such a strip moves the file of the draft to the published version. Only what changed is written.
     *
     * The caller holds the row of the page locked (ChangeDraft, RebuildMediaUsage, SetPageShareImage), so two changes
     * never write the rows of a page at the same time. The places of the page itself follow the attributes of the model
     * it hands, saved or not, so SetPageShareImage gives a file its place before it stores the column.
     */
    public function handle(Page $page): void
    {
        $stored = MediaUsage::query()->onPage($page)->get();

        $wanted = $this->places($page, UsageVersion::Published, PageStrips::fromArray($page->strips), $stored);

        if ($page->draft_strips !== null) {
            $wanted = [...$wanted, ...$this->places($page, UsageVersion::Draft, PageStrips::fromArray($page->draft_strips), $stored)];
        }

        $wanted = [...$wanted, ...self::own($page)];

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
            $files = $this->types->has($strip->type)
                ? $this->types->find($strip->type)->places($strip->values)
                : self::unreadable($stored, $strip);

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
     * The places of the page itself, outside its strips: one row each, without a strip.
     *
     * @return array<string, Place>
     */
    private static function own(Page $page): array
    {
        if ($page->share_image_id === null) {
            return [];
        }

        $field = PagePlace::ShareImage->value;

        return [self::key(UsageVersion::Page->value, '', $field) => [
            'media_id' => $page->share_image_id,
            'page_id' => $page->id,
            'version' => UsageVersion::Page->value,
            'strip_id' => '',
            'strip_type' => '',
            'field' => $field,
        ]];
    }

    /**
     * The files of a strip whose type left the catalogue: at every place its rows in either version know, the file its
     * values hold there, read as a field that holds a file stores its id. Such a strip cannot change while its type is
     * gone, but publishing hands the values of its draft to the published version.
     *
     * @param  Collection<int, MediaUsage>  $stored
     * @return array<string, int>
     */
    private static function unreadable(Collection $stored, Strip $strip): array
    {
        $files = [];

        foreach ($stored as $usage) {
            if ($usage->version === UsageVersion::Page || $usage->strip_id !== $strip->id || $usage->strip_type !== $strip->type || array_key_exists($usage->field, $files)) {
                continue;
            }

            $files[$usage->field] = MediaChoice::id(self::valueAt($strip->values, $usage->field));
        }

        return array_filter($files, static fn (?int $media): bool => $media !== null);
    }

    /**
     * The value at the path of a place: a field by its key, an item of a list by its id, or by its position when no
     * item has that id (an item stored without one).
     *
     * @param  array<string, mixed>  $values
     */
    private static function valueAt(array $values, string $path): mixed
    {
        $value = $values;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value)) {
                return null;
            }

            if (array_is_list($value)) {
                $item = array_find($value, static fn (mixed $item): bool => is_array($item) && ($item['id'] ?? null) === $segment);
                $value = $item ?? (ctype_digit($segment) ? $value[(int) $segment] ?? null : null);
            } else {
                $value = $value[$segment] ?? null;
            }
        }

        return $value;
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

        // A shared lock on the files, so a file that is being deleted gets no place, and one read here is not
        // deleted before this place is committed (DeleteMedia).
        $known = array_flip(Media::query()->lockedForUsage($ids)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        return array_filter($places, static fn (array $place): bool => isset($known[$place['media_id']]));
    }

    private static function key(string $version, string $stripId, string $field): string
    {
        return "{$version}\0{$stripId}\0{$field}";
    }
}
