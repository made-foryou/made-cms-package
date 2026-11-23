<?php

declare(strict_types=1);

use Made\CmsPackage\Content\PageTypes\PageTypes;

if (is_dir(__DIR__.'/../../src/Content/PageTypes')) {
    arch('the page type catalogue is final')
        ->expect(PageTypes::class)
        ->toBeFinal();

    arch('page type definitions only describe: they never write, dispatch or reach for facades')
        ->expect('Made\CmsPackage\Content\PageTypes')
        ->not->toUse([
            'Made\CmsPackage\Content\Actions',
            'Made\CmsPackage\Content\Events',
            'Illuminate\Support\Facades',
        ]);
}
