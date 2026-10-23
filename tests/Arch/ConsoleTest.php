<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

if (is_dir(__DIR__.'/../../src/Console/Commands')) {
    arch('commands are final artisan commands suffixed with Command')
        ->expect('Made\CmsPackage\Console\Commands')
        ->toBeClasses()
        ->toBeFinal()
        ->toExtend(Command::class)
        ->toHaveSuffix('Command');
}

if (is_dir(__DIR__.'/../../src/Console')) {
    arch('the console layer writes through the contexts, never through Eloquent directly')
        ->expect('Made\CmsPackage\Console')
        ->not->toUse([Model::class, Builder::class, 'Illuminate\Support\Facades\DB']);
}
