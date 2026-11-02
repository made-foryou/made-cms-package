<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\StripTypes;

if (is_dir(__DIR__.'/../../src/Content/Strips/Fields')) {
    arch('fields extend the field base')
        ->expect('Made\CmsPackage\Content\Strips\Fields')
        ->classes()
        ->toExtend(Field::class)
        ->ignoring(Field::class);

    arch('fields other than the shared base are final')
        ->expect('Made\CmsPackage\Content\Strips\Fields')
        ->classes()
        ->toBeFinal()
        ->ignoring(Field::class);
}

if (is_dir(__DIR__.'/../../src/Content/Strips')) {
    arch('the strip type catalogue is final')
        ->expect(StripTypes::class)
        ->toBeFinal();

    arch('strip definitions only describe: they never write, dispatch or reach for facades')
        ->expect('Made\CmsPackage\Content\Strips')
        ->not->toUse([
            'Made\CmsPackage\Content\Actions',
            'Made\CmsPackage\Content\Events',
            'Illuminate\Support\Facades',
        ]);
}
