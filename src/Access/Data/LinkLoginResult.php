<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * A login through the mail link, with the page of the site where the editor asked for the code.
 */
final readonly class LinkLoginResult
{
    public function __construct(
        public LoginResult $result,
        public ?string $returnTo,
    ) {}
}
