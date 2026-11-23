<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\PageTypes\Valid;

use Made\CmsPackage\Content\PageTypes\PageType;

/**
 * Shared base of the site's page types; the catalogue skips it because it is abstract.
 */
abstract class BasePage extends PageType {}
