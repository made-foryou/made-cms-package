<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LauncherVisitor;
use Made\CmsPackage\Access\Enums\LauncherView;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Decides what the CMS launcher shows a visitor. The tile only hides the door, it never grants access;
 * a logged-in editor always gets the edit bar, so logging out stays possible from anywhere.
 */
final class ShowLauncher
{
    use AsAction;

    public function handle(LauncherVisitor $visitor): LauncherView
    {
        if (! config()->boolean('made-cms-package.launcher.enabled', true)) {
            return LauncherView::Hidden;
        }

        if ($visitor->editor !== null) {
            return LauncherView::EditBar;
        }

        if ($visitor->remembered) {
            return LauncherView::Tile;
        }

        /** @var list<string> $allowedIps */
        $allowedIps = config()->array('made-cms-package.launcher.allowed_ips', []);

        if ($visitor->ip === null || $allowedIps === []) {
            return LauncherView::Hidden;
        }

        return IpUtils::checkIp($visitor->ip, $allowedIps) ? LauncherView::Tile : LauncherView::Hidden;
    }
}
