<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\ShareImageRefusal;

/**
 * The image a shared link to a page shows, and where it comes from: the share image of the page itself, else the
 * default of the site.
 */
final readonly class ShareImageDescription
{
    /**
     * @param  MediaFile|null  $file  the file the page holds as its share image, also when a shared link cannot show it
     * @param  ShareImageRefusal|null  $refusal  why the page holds a file a shared link cannot show; null when it shows it or holds none
     * @param  ShareImage|null  $own  the share image of the page, when a shared link can show it
     * @param  ShareImage|null  $fallback  the default image of the site (config meta.share_image); null for none
     */
    public function __construct(
        public ?MediaFile $file,
        public ?ShareImageRefusal $refusal,
        public ?ShareImage $own,
        public ?ShareImage $fallback,
    ) {}

    /**
     * The image a shared link shows: the share image of the page, else the default of the site, else none.
     */
    public function shown(): ?ShareImage
    {
        return $this->own ?? $this->fallback;
    }
}
