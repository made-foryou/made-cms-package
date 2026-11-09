<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\RecogniseDevice;
use Made\CmsPackage\Access\Actions\RememberDevice;
use Made\CmsPackage\Access\Data\KnownDevice;
use Made\CmsPackage\Access\Data\LoginStart;
use Made\CmsPackage\Access\Enums\LoginMethod;

function deviceOf(string $email): KnownDevice
{
    return new KnownDevice(emailHash: RememberDevice::make()->emailHash($email), firstName: 'Menno', method: LoginMethod::Code);
}

it('recognises a device that logged in with the typed address', function (string $typed): void {
    expect(RecogniseDevice::run(new LoginStart($typed, deviceOf('menno@made-foryou.nl'))))->toBeTrue();
})->with([
    'as typed then' => 'menno@made-foryou.nl',
    'with capitals and spaces' => ' Menno@Made-ForYou.nl ',
]);

it('does not recognise a device of another address or no device at all', function (?KnownDevice $device): void {
    expect(RecogniseDevice::run(new LoginStart('menno@made-foryou.nl', $device)))->toBeFalse();
})->with([
    'another address' => fn (): KnownDevice => deviceOf('iemand@example.com'),
    'no device' => null,
]);
