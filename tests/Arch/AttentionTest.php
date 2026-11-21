<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Attention\AttentionSource;

arch('the sources of attention are attention sources')
    ->expect('Made\CmsPackage\Content\Attention')
    ->classes()
    ->toImplement(AttentionSource::class)
    ->ignoring(AttentionSource::class);

arch('the sources of attention are final')
    ->expect('Made\CmsPackage\Content\Attention')
    ->classes()
    ->toBeFinal()
    ->ignoring(AttentionSource::class);

arch('the sources of attention are readonly')
    ->expect('Made\CmsPackage\Content\Attention')
    ->classes()
    ->toBeReadonly()
    ->ignoring(AttentionSource::class);

// A source only reads what a page holds; whatever is written or told about it belongs to the Actions.
arch('the sources of attention only read: they never dispatch or reach for facades')
    ->expect('Made\CmsPackage\Content\Attention')
    ->not->toUse([
        'Made\CmsPackage\Content\Events',
        'Illuminate\Support\Facades',
    ]);
