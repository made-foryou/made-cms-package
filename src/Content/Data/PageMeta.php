<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What the head of a page tells Google and an app that shows a shared link to it: its title, its description, its
 * canonical address, whether Google may show it, the image of a shared link and the name of the site.
 *
 * @phpstan-import-type SharedImage from ShareImage
 */
final readonly class PageMeta
{
    /**
     * @param  string  $title  the title of its own, else the fallback (MakeFallbackTitle)
     * @param  string|null  $description  null leaves the description to Google
     * @param  string|null  $canonical  the absolute address from the url of the site; null for a page without an address
     * @param  bool  $indexable  false is noindex
     * @param  ShareImage|null  $image  the image of a shared link (DescribeShareImage); null for none
     * @param  string|null  $siteName  the name of the site (og:site_name)
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $canonical,
        public bool $indexable,
        public ?ShareImage $image = null,
        public ?string $siteName = null,
    ) {}

    /**
     * The meta as a prop for Inertia, Vue or React.
     *
     * @return array{title: string, description: string|null, canonical: string|null, indexable: bool, image: SharedImage|null, site_name: string|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'indexable' => $this->indexable,
            'image' => $this->image?->toArray(),
            'site_name' => $this->siteName,
        ];
    }
}
