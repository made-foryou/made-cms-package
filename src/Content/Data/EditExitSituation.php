<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\PageStatus;

/**
 * What decides where an editor goes when they stop editing a page.
 */
final readonly class EditExitSituation
{
    /**
     * @param  PageStatus  $status  the status of the page now
     * @param  bool  $hasAddress  whether the page has a row in `cms_routes`
     * @param  bool  $publishing  whether the editor publishes the draft on the way out
     */
    public function __construct(
        public PageStatus $status,
        public bool $hasAddress,
        public bool $publishing,
    ) {}
}
