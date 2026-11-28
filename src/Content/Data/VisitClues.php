<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What a request tells about where a visit came from. Only to decide the source: it is never stored.
 */
final readonly class VisitClues
{
    /**
     * @param  list<string>  $ownHosts  the hosts of the site itself, so a link on it counts as internal
     */
    public function __construct(
        public ?string $userAgent,
        public ?string $referrer,
        public array $ownHosts,
    ) {}
}
