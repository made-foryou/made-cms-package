<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Made\CmsPackage\Content\Actions\RenderStrips;
use Made\CmsPackage\Content\Data\RenderedStrip;
use Made\CmsPackage\Content\Exceptions\MissingStripView;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;

/**
 * <x-made-cms::strips :page="$page" />: every strip a visitor sees, each rendered with the view
 * of its strip type ("strips.{key}" or its view()) and the strip as $strip.
 */
final class Strips extends Component
{
    /**
     * The services come first: Blade only passes the page, the container fills in the rest.
     */
    public function __construct(
        private readonly StripTypes $types,
        private readonly Factory $views,
        private readonly Page $page,
    ) {}

    /**
     * Every view is looked up before the first strip renders, so a missing one never leaves half a page.
     *
     * @throws MissingStripView
     */
    public function render(): Htmlable
    {
        $strips = RenderStrips::run($this->page)->strips;
        $views = array_map($this->viewFor(...), $strips);
        $html = '';

        foreach ($strips as $index => $strip) {
            $html .= $this->views->make($views[$index], ['strip' => $strip])->render();
        }

        return new HtmlString($html);
    }

    /**
     * @return view-string
     */
    private function viewFor(RenderedStrip $strip): string
    {
        $type = $this->types->find($strip->type);
        $view = $type->view() ?? 'strips.'.$type->key();

        if (! $this->views->exists($view)) {
            throw MissingStripView::for($type, $view);
        }

        /** @var view-string $view */
        return $view;
    }
}
