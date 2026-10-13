<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use debug, env or exit helpers')
    ->expect(['dd', 'ddd', 'dump', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Made\CmsPackage')
    ->toUseStrictTypes();
