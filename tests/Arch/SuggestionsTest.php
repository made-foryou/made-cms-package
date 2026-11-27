<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Suggestions\DescriptionSource;

arch('the sources of suggestions are description sources')
    ->expect('Made\CmsPackage\Content\Suggestions')
    ->classes()
    ->toImplement(DescriptionSource::class)
    ->ignoring(DescriptionSource::class);

arch('the sources of suggestions are final')
    ->expect('Made\CmsPackage\Content\Suggestions')
    ->classes()
    ->toBeFinal()
    ->ignoring(DescriptionSource::class);

arch('the sources of suggestions are readonly')
    ->expect('Made\CmsPackage\Content\Suggestions')
    ->classes()
    ->toBeReadonly()
    ->ignoring(DescriptionSource::class);

// A source only reads what a page holds; it proposes, the editor decides and an Action stores.
arch('the sources of suggestions only read: they never dispatch or reach for facades')
    ->expect('Made\CmsPackage\Content\Suggestions')
    ->not->toUse([
        'Made\CmsPackage\Content\Events',
        'Illuminate\Support\Facades',
    ]);
