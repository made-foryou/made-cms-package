<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

if (is_dir(__DIR__.'/../../src/Http/Controllers')) {
    arch('controllers are final and suffixed with Controller')
        ->expect('Made\CmsPackage\Http\Controllers')
        ->toBeClasses()
        ->toBeFinal()
        ->toHaveSuffix('Controller');
}

if (is_dir(__DIR__.'/../../src/Http/Middleware')) {
    arch('middleware is final and handles a request')
        ->expect('Made\CmsPackage\Http\Middleware')
        ->toBeClasses()
        ->toBeFinal()
        ->toHaveMethod('handle');
}

if (is_dir(__DIR__.'/../../src/Http')) {
    arch('the http layer reads and writes through the contexts, never through Eloquent directly')
        ->expect('Made\CmsPackage\Http')
        ->not->toUse([Model::class, Builder::class, 'Illuminate\Support\Facades\DB']);
}
