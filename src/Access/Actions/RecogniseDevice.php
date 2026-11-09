<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LoginStart;

/**
 * Decides whether this device logged in with the typed address before. It never looks the address up, so the
 * answer tells nobody whether an account exists; it only proves that the browser knew the address already.
 */
final class RecogniseDevice
{
    use AsAction;

    public function handle(LoginStart $start): bool
    {
        return $start->device !== null
            && hash_equals($start->device->emailHash, RememberDevice::make()->emailHash($start->email));
    }
}
