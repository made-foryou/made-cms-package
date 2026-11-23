<?php

declare(strict_types=1);

use Made\CmsPackage\Content\PageTypes\PageType;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Content\Strips\StripTypes;

it('ships valid example page types in the workbench', function (): void {
    $workbench = __DIR__.'/../../../workbench/app/Cms';
    $catalogue = PageTypes::discover($workbench.'/PageTypes', 'Workbench\App\Cms\PageTypes', StripTypes::discover($workbench.'/Strips', 'Workbench\App\Cms\Strips'));

    // In the order of their labels: Landingspagina, Teampagina.
    expect(array_map(static fn (PageType $type): string => $type->key(), $catalogue->all()))
        ->toBe(['landing', 'team']);
});
