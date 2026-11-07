<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
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

// A listener of the content never sees a change that a rollback still takes back: an event dispatched in a
// transaction (ChangeDraft, a transaction of the host around an Action) goes out after the commit.
arch('content events wait for the commit')
    ->expect('Made\CmsPackage\Content\Events')
    ->toImplement(ShouldDispatchAfterCommit::class);

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
