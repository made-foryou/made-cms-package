<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Content\Strips\StripTypes;

it('ships valid example strip types in the workbench', function (): void {
    $catalogue = StripTypes::discover(__DIR__.'/../../../workbench/app/Cms/Strips', 'Workbench\App\Cms\Strips');

    // In the order of their labels: Downloads, Fotogalerij, Hero, Kaarten.
    expect(array_map(static fn (StripType $type): string => $type->key(), $catalogue->all()))
        ->toBe(['downloads', 'gallery', 'hero', 'cards']);
});
