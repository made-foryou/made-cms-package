<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * A click on the link in the login mail, in a browser with this binding (none when no login is in progress there).
 */
final readonly class LinkLogin
{
    public function __construct(
        public string $token,
        public ?string $binding,
    ) {}
}
