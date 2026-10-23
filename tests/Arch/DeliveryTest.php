<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;

$listeners = contextNamespaces('Listeners');
$notifications = contextNamespaces('Notifications');

if ($listeners !== []) {
    arch('listeners are final and run synchronously')
        ->expect($listeners)
        ->toBeClasses()
        ->toBeFinal()
        ->toHaveMethod('handle')
        ->not->toImplement(ShouldQueue::class);
}

if ($notifications !== []) {
    arch('notifications are final and never queued, so no secret ends up in a queue payload')
        ->expect($notifications)
        ->toBeClasses()
        ->toBeFinal()
        ->not->toImplement(ShouldQueue::class);
}
