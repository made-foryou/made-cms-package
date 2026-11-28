<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The rules of robots.txt Googlebot follows (for every robot or for Googlebot itself): what keeps an address out of
 * the sitemap, for the sitemap and for the screen that says why a page is not in it.
 */
final readonly class GoogleRobotsRules
{
    /**
     * @param  list<RobotsRule>  $rules
     */
    public function __construct(
        public array $rules,
    ) {}

    /**
     * Whether a rule keeps Googlebot away from the address, with the prefix of its route group.
     */
    public function blocks(string $address): bool
    {
        return array_any($this->rules, static fn (RobotsRule $rule): bool => $rule->matches($address));
    }
}
