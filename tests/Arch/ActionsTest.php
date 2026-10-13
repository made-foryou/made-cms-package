<?php

declare(strict_types=1);

use Lorisleiva\Actions\Concerns\AsAction;

$actions = contextNamespaces('Actions');

if ($actions !== []) {
    arch('actions are final laravel-actions with a handle method')
        ->expect($actions)
        ->toBeClasses()
        ->toBeFinal()
        ->toExtendNothing()
        ->toUseTrait(AsAction::class)
        ->toHaveMethod('handle');

    arch('actions do not use the laravel-actions transport hooks')
        ->expect($actions)
        ->not->toHaveMethod('asController')
        ->not->toHaveMethod('asListener')
        ->not->toHaveMethod('asJob')
        ->not->toHaveMethod('asCommand')
        ->not->toHaveMethod('rules');

    arch('actions are transport-agnostic')
        ->expect($actions)
        ->not->toUse([
            'Illuminate\Http',
            'Illuminate\Support\Facades\Request',
            'Illuminate\Support\Facades\Auth',
        ]);
}
