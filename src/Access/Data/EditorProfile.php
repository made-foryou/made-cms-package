<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * How the CMS addresses an editor: by first name, with an initial for the avatar.
 */
final readonly class EditorProfile
{
    public function __construct(
        public ?string $firstName,
        public string $initial,
    ) {}
}
