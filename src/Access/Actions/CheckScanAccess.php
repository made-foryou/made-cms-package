<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\ScanVisit;
use Made\CmsPackage\Access\Enums\Role;

/**
 * Decides whether the scan opens. It writes code and content into the site, so only an admin gets in, and only on the
 * machine of a developer (APP_ENV=local). The scan has no screen of its own.
 */
final class CheckScanAccess
{
    use AsAction;

    public function handle(ScanVisit $visit): bool
    {
        if ($visit->editor === null || ! app()->isLocal()) {
            return false;
        }

        return $visit->editor->roles->containsStrict(Role::Admin);
    }
}
