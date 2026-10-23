<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * The details of a CMS account to create. Without a password the editor logs in with a code.
 */
final readonly class NewEditor
{
    public function __construct(
        public string $email,
        public string $name,
        public ?string $password,
    ) {}
}
