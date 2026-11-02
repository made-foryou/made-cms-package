<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Strips\Valid;

use Made\CmsPackage\Content\Strips\StripType;

/**
 * Shared base of the site's strip types; the catalogue skips it because it is abstract.
 */
abstract class BaseStrip extends StripType {}
