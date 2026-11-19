<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\ShareImage;
use Made\CmsPackage\Content\Data\ShareImageDescription;
use Made\CmsPackage\Content\Enums\ShareImageRefusal;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Support\Site;

final class DescribeShareImage
{
    use AsAction;

    /**
     * The image a shared link to a page shows: its share image, as the photo the site shows (never the thumbnail or
     * the original) at an address absolute from the url of the site, with its size and alt text; else the default of
     * the site (config meta.share_image); else none. A file that is gone, no image or an svg falls back and says why,
     * and so does a media disk a visitor cannot open (reported): the head of a page never throws.
     */
    public function handle(Page $page): ShareImageDescription
    {
        $fallback = self::fallback();
        $id = $page->share_image_id;

        if ($id === null) {
            return new ShareImageDescription(file: null, refusal: null, own: null, fallback: $fallback);
        }

        $file = self::find($id);
        $refusal = ShareImageRefusal::of($file?->kind, $file?->mime);
        $url = $file === null || $refusal !== null ? null : Site::absolute($file->url);

        return new ShareImageDescription(
            file: $file,
            refusal: $refusal,
            own: $file === null || $url === null ? null : new ShareImage($url, $file->width, $file->height, self::oneLine($file->alt)),
            fallback: $fallback,
        );
    }

    private static function find(int $id): ?MediaFile
    {
        try {
            return FindMedia::run([$id])->find($id);
        } catch (MediaDiskNotUsable $unusable) {
            report($unusable);

            return null;
        }
    }

    /**
     * The default image of the site from config: an address (absolute, or from the root of the site), or an array
     * with url, width, height and alt. What it cannot read it leaves out; without an address there is none.
     */
    private static function fallback(): ?ShareImage
    {
        $config = config('made-cms-package.meta.share_image');
        $config = is_string($config) ? ['url' => $config] : $config;

        if (! is_array($config) || ! is_string($config['url'] ?? null)) {
            return null;
        }

        $url = Site::absolute(trim($config['url']));

        return $url === null ? null : new ShareImage(
            url: $url,
            width: self::pixels($config['width'] ?? null),
            height: self::pixels($config['height'] ?? null),
            alt: is_string($config['alt'] ?? null) ? self::oneLine($config['alt']) : null,
        );
    }

    private static function pixels(mixed $value): ?int
    {
        $pixels = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;

        return $pixels > 0 ? $pixels : null;
    }

    private static function oneLine(?string $text): ?string
    {
        $text = Str::squish((string) $text);

        return $text === '' ? null : $text;
    }
}
