<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LauncherVisitor;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Decides whether the CMS launcher is shown to a visitor. This only hides the door, it never grants access.
 */
final class ShowLauncher
{
    use AsAction;

    public function handle(LauncherVisitor $visitor): bool
    {
        if (! config()->boolean('made-cms-package.launcher.enabled', true)) {
            return false;
        }

        if ($visitor->remembered) {
            return true;
        }

        /** @var list<string> $allowedIps */
        $allowedIps = config()->array('made-cms-package.launcher.allowed_ips', []);

        if ($visitor->ip === null || $allowedIps === []) {
            return false;
        }

        return IpUtils::checkIp($visitor->ip, $allowedIps);
    }
}
