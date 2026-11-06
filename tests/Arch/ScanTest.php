<?php

declare(strict_types=1);

if (is_dir(__DIR__.'/../../src/Content/Scan')) {
    arch('the scan machinery is final')
        ->expect('Made\CmsPackage\Content\Scan')
        ->classes()
        ->toBeFinal();

    arch('the scan only reads: it never writes, dispatches, queries or reaches for facades')
        ->expect('Made\CmsPackage\Content\Scan')
        ->not->toUse([
            'Made\CmsPackage\Content\Actions',
            'Made\CmsPackage\Content\Events',
            'Made\CmsPackage\Content\Models',
            'Made\CmsPackage\Content\Builders',
            'Illuminate\Support\Facades',
        ]);
}
