<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Content\Actions\DecideEditExit;
use Made\CmsPackage\Content\Data\EditExitSituation;
use Made\CmsPackage\Content\Enums\EditExit;
use Made\CmsPackage\Content\Models\Page;

/**
 * Where an editor lands when they stop editing a page, as the page stands now: DecideEditExit decides, this gives the
 * addresses. The bar asks again after every change of the draft, so publishing moves the way out to the site.
 */
final readonly class EditExits
{
    public function __construct(
        public string $stop,
        public string $publish,
        public bool $stopsAtPages,
    ) {}

    public static function of(Page $page): self
    {
        $pages = PageLinks::show($page);
        $site = SiteAddress::of($page);
        $hasAddress = $site !== null;
        $situation = static fn (bool $publishing): EditExitSituation => new EditExitSituation($page->status, $hasAddress, $publishing);

        $stop = DecideEditExit::run($situation(false));
        $publish = DecideEditExit::run($situation(true));

        return new self(
            stop: $stop === EditExit::Site && $site !== null ? $site : $pages,
            publish: $publish === EditExit::Site && $site !== null ? $site : $pages,
            stopsAtPages: $stop === EditExit::Pages,
        );
    }

    /** @return array{stop: string, publish: string} */
    public function toArray(): array
    {
        return ['stop' => $this->stop, 'publish' => $this->publish];
    }
}
