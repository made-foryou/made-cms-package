<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Component;
use Made\CmsPackage\Http\Requests\LauncherRequest;

if (is_dir(__DIR__.'/../../src/Http/Controllers')) {
    arch('controllers are final and suffixed with Controller')
        ->expect('Made\CmsPackage\Http\Controllers')
        ->toBeClasses()
        ->toBeFinal()
        ->toHaveSuffix('Controller');

    arch('controllers leave validation and its messages to form requests')
        ->expect('Made\CmsPackage\Http\Controllers')
        ->not->toUse([ValidationException::class, 'Illuminate\Support\Facades\Validator']);
}

if (is_dir(__DIR__.'/../../src/Http/Requests')) {
    arch('form requests are final, suffixed with Request and turn their input into context data')
        ->expect('Made\CmsPackage\Http\Requests')
        ->toBeClasses()
        ->toExtend(FormRequest::class)
        ->toHaveSuffix('Request');

    arch('form requests other than the shared base are final')
        ->expect('Made\CmsPackage\Http\Requests')
        ->classes()
        ->toBeFinal()
        ->ignoring(LauncherRequest::class);
}

if (is_dir(__DIR__.'/../../src/Http/Middleware')) {
    arch('middleware is final and handles a request')
        ->expect('Made\CmsPackage\Http\Middleware')
        ->toBeClasses()
        ->toBeFinal()
        ->toHaveMethod('handle');
}

if (is_dir(__DIR__.'/../../src/Http/Components')) {
    arch('blade components for the site are final view components')
        ->expect('Made\CmsPackage\Http\Components')
        ->toBeClasses()
        ->toBeFinal()
        ->toExtend(Component::class);
}

if (is_dir(__DIR__.'/../../src/Http')) {
    arch('the http layer reads and writes through the contexts, never through Eloquent directly')
        ->expect('Made\CmsPackage\Http')
        ->not->toUse([Model::class, Builder::class, 'Illuminate\Support\Facades\DB']);
}
