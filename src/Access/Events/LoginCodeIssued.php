<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Access\Data\IssuedLoginCode;

/**
 * Carries the plain code for delivery. Handle it synchronously so the code never ends up in a queue payload.
 */
final class LoginCodeIssued
{
    use Dispatchable;

    public function __construct(
        public readonly IssuedLoginCode $issued,
    ) {}
}
