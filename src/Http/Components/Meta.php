<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;
use Made\CmsPackage\Content\Actions\DescribePageMeta;
use Made\CmsPackage\Content\Models\Page;

/**
 * <x-made-cms::meta :page="$page" />: what the head of a page tells Google, from DescribePageMeta. The title, the
 * description (only when the page has one), the canonical address, noindex when Google may not show the page, and the
 * name of the page for the edit bar, which otherwise reads it from a title of its own.
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

        return implode("\n", array_filter([
            '<title>'.e($meta->title).'</title>',
            $meta->description === null ? null : '<meta name="description" content="'.e($meta->description).'">',
            $meta->canonical === null ? null : '<link rel="canonical" href="'.e($meta->canonical).'">',
            $meta->indexable ? null : '<meta name="robots" content="noindex">',
            '<meta name="made-cms:page" content="'.e($this->page->title).'">',
        ]));
    }
}
