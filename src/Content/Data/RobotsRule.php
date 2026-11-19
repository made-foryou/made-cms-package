<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\RobotsRuleOrigin;
use Made\CmsPackage\Content\Sitemap\RobotsPattern;

/**
 * A Disallow rule of robots.txt: the robots it is for, the path it keeps them away from, and who set it.
 */
final readonly class RobotsRule
{
    public function __construct(
        public string $userAgent,
        public string $path,
        public RobotsRuleOrigin $origin,
    ) {}

    /**
     * Whether Googlebot follows the rule: one for every robot or for Googlebot itself. A rule for another robot keeps
     * that robot away only, so it never takes a page out of the sitemap.
     */
    public function concernsGoogle(): bool
    {
        return $this->userAgent === '*' || strcasecmp($this->userAgent, 'Googlebot') === 0;
    }

    public function matches(string $path): bool
    {
        return (new RobotsPattern($this->path))->matches($path);
    }
}
