<?php

declare(strict_types=1);

use Made\CmsPackage\Support\Device;

it('recognises the device and browser of a user agent', function (?string $userAgent, ?string $device, ?string $browser): void {
    expect(Device::from($userAgent))->toBe(['device' => $device, 'browser' => $browser]);
})->with([
    'safari on a mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15', 'mac', 'Safari'],
    'chrome on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 'windows', 'Chrome'],
    'edge on windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0', 'windows', 'Edge'],
    'safari on an iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'iphone', 'Safari'],
    'chrome on an iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/130.0 Mobile/15E148 Safari/604.1', 'iphone', 'Chrome'],
    'firefox on android' => ['Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0', 'android', 'Firefox'],
    'firefox on linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'linux', 'Firefox'],
    'unknown' => ['curl/8.4.0', null, null],
    'missing' => [null, null, null],
]);
