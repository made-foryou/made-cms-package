<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\OfferLoginMethods;
use Made\CmsPackage\Access\Actions\RememberDevice;
use Made\CmsPackage\Access\Data\KnownDevice;
use Made\CmsPackage\Access\Data\LoginStart;
use Made\CmsPackage\Access\Enums\LoginMethod;

function knownDeviceFor(string $email, string $firstName = 'Menno', ?LoginMethod $method = LoginMethod::Password): KnownDevice
{
    return new KnownDevice(
        emailHash: RememberDevice::make()->emailHash($email),
        firstName: $firstName,
        method: $method,
    );
}

it('offers the code and the password to everyone', function (): void {
    $options = OfferLoginMethods::run(new LoginStart(email: 'menno@made-foryou.nl', device: null));

    expect($options->methods)->toBe([LoginMethod::Code, LoginMethod::Password])
        ->and($options->firstName)->toBeNull()
        ->and($options->preferred)->toBeNull();
});

it('greets the editor and preselects the last method on a device that logged in with this address', function (): void {
    $options = OfferLoginMethods::run(new LoginStart(
        email: ' Menno@Made-ForYou.nl ',
        device: knownDeviceFor('menno@made-foryou.nl'),
    ));

    expect($options->firstName)->toBe('Menno')
        ->and($options->preferred)->toBe(LoginMethod::Password)
        ->and($options->methods)->toBe([LoginMethod::Code, LoginMethod::Password]);
});

it('stays neutral when the device remembers another address', function (): void {
    $options = OfferLoginMethods::run(new LoginStart(
        email: 'iemand@example.com',
        device: knownDeviceFor('menno@made-foryou.nl'),
    ));

    expect($options->firstName)->toBeNull()
        ->and($options->preferred)->toBeNull();
});
