<?php

declare(strict_types=1);

use Illuminate\Foundation\Events\Dispatchable;

$data = contextNamespaces('Data');
$events = contextNamespaces('Events');
$enums = contextNamespaces('Enums');
$exceptions = contextNamespaces('Exceptions');

if ($data !== []) {
    arch('data objects are final readonly plain classes')
        ->expect($data)
        ->toBeClasses()
        ->toBeFinal()
        ->toBeReadonly()
        ->toExtendNothing()
        ->not->toUse([
            'Illuminate\Support\Facades',
            'Illuminate\Database\Eloquent\Builder',
        ]);
}

if ($events !== []) {
    arch('events are final and dispatchable')
        ->expect($events)
        ->toBeClasses()
        ->toBeFinal()
        ->toUseTrait(Dispatchable::class)
        ->not->toUse([
            ...contextNamespaces('Actions'),
            'Illuminate\Support\Facades',
        ]);
}

if ($enums !== []) {
    arch('enums are string backed')
        ->expect($enums)
        ->toBeStringBackedEnums();
}

if ($exceptions !== []) {
    arch('exceptions are final throwables')
        ->expect($exceptions)
        ->toBeClasses()
        ->toBeFinal()
        ->toImplement(Throwable::class);
}
