<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Who set a rule of robots.txt.
 */
enum RobotsRuleOrigin: string
{
    /** The developer, in config (`made-cms-package.sitemap.robots`). */
    case Config = 'config';
}
