<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LoginOptions;
use Made\CmsPackage\Access\Data\LoginStart;
use Made\CmsPackage\Access\Enums\LoginMethod;

/**
 * Decides how the login continues after the e-mail step. It never looks the address up, so the answer
 * tells nobody whether an account exists: only a device that logged in with this address gets a greeting.
 */
final class OfferLoginMethods
{
    use AsAction;

    public function handle(LoginStart $start): LoginOptions
    {
        $device = $start->device;
        $known = $device !== null && RecogniseDevice::run($start);

        return new LoginOptions(
            firstName: $known ? $device->firstName : null,
            preferred: $known ? $device->method : null,
            methods: [LoginMethod::Code, LoginMethod::Password],
        );
    }
}
