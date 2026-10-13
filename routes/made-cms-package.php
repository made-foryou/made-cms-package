<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/** @var string $prefix */
$prefix = config('made-cms-package.routes.prefix');

/** @var list<string> $middleware */
$middleware = config('made-cms-package.routes.middleware');

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('made-cms-package.')
    ->group(function (): void {
        // CMS routes are added here together with their first Http feature.
    });
