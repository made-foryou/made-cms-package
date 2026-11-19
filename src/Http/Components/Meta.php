<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;
use Made\CmsPackage\Content\Actions\DescribePageMeta;
use Made\CmsPackage\Content\Models\Page;

/**
 * <x-made-cms::meta :page="$page" />: what the head of a page tells Google and an app that shows a shared link to it,
 * from DescribePageMeta. The title, the description (only when the page has one), the canonical address, noindex when
 * Google may not show the page, the Open Graph tags and the card for a shared link (also on a noindex page: sharing is
 * apart from search), and the name of the page for the edit bar, which otherwise reads it from a title of its own.
 */
final class Meta extends Component implements Htmlable
{
    public function __construct(
        public readonly Page $page,
    ) {}

    public function render(): Htmlable
    {
        return $this;
    }

    public function toHtml(): string
    {
        $meta = DescribePageMeta::run($this->page);
        $image = $meta->image;
        $sized = $image?->width !== null && $image->height !== null;

        return implode("\n", array_filter([
            '<title>'.e($meta->title).'</title>',
            $meta->description === null ? null : '<meta name="description" content="'.e($meta->description).'">',
            $meta->canonical === null ? null : '<link rel="canonical" href="'.e($meta->canonical).'">',
            $meta->indexable ? null : '<meta name="robots" content="noindex">',
            self::property('og:type', 'website'),
            $meta->siteName === null ? null : self::property('og:site_name', $meta->siteName),
            self::property('og:title', $meta->title),
            $meta->description === null ? null : self::property('og:description', $meta->description),
            $meta->canonical === null ? null : self::property('og:url', $meta->canonical),
            $image === null ? null : self::property('og:image', $image->url),
            $sized ? self::property('og:image:width', (string) $image->width) : null,
            $sized ? self::property('og:image:height', (string) $image->height) : null,
            $image?->alt === null ? null : self::property('og:image:alt', $image->alt),
            '<meta name="twitter:card" content="'.($image === null ? 'summary' : 'summary_large_image').'">',
            '<meta name="made-cms:page" content="'.e($this->page->title).'">',
        ]));
    }

    private static function property(string $property, string $content): string
    {
        return '<meta property="'.$property.'" content="'.e($content).'">';
    }
}
