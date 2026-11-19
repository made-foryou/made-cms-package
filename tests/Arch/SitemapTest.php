<?php

declare(strict_types=1);

arch('the sitemap machinery is final')
    ->expect('Made\CmsPackage\Content\Sitemap')
    ->classes()
    ->toBeFinal();

// What goes in the sitemap is read and written as xml here; storing it, and the events after it, belong to the Actions.
arch('the sitemap machinery only reads: it never stores, dispatches or reaches for facades')
    ->expect('Made\CmsPackage\Content\Sitemap')
    ->not->toUse([
        'Made\CmsPackage\Content\Actions',
        'Made\CmsPackage\Content\Events',
        'Illuminate\Support\Facades',
    ]);
