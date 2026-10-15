<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\ShowLauncher;
use Made\CmsPackage\Access\Data\LauncherVisitor;

it('shows the launcher to a visitor from an allowed ip address', function (string $ip, array $allowed): void {
    config(['made-cms-package.launcher.allowed_ips' => $allowed]);

    expect(ShowLauncher::run(new LauncherVisitor(ip: $ip, remembered: false)))->toBeTrue();
})->with([
    'exact ipv4' => ['203.0.113.5', ['203.0.113.5']],
    'ipv4 range' => ['203.0.113.77', ['198.51.100.1', '203.0.113.0/24']],
    'exact ipv6' => ['2001:db8::1', ['2001:db8::1']],
    'ipv6 range' => ['2001:db8::abcd', ['2001:db8::/32']],
]);

it('shows the launcher to a remembered editor from any ip address', function (): void {
    config(['made-cms-package.launcher.allowed_ips' => []]);

    expect(ShowLauncher::run(new LauncherVisitor(ip: '198.51.100.20', remembered: true)))->toBeTrue();
});

it('hides the launcher from an unknown visitor', function (?string $ip, array $allowed): void {
    config(['made-cms-package.launcher.allowed_ips' => $allowed]);

    expect(ShowLauncher::run(new LauncherVisitor(ip: $ip, remembered: false)))->toBeFalse();
})->with([
    'ip outside the allowlist' => ['198.51.100.20', ['203.0.113.0/24']],
    'empty allowlist' => ['203.0.113.5', []],
    'unknown ip' => [null, ['203.0.113.5']],
]);

it('hides the launcher from everyone when it is disabled', function (): void {
    config([
        'made-cms-package.launcher.enabled' => false,
        'made-cms-package.launcher.allowed_ips' => ['203.0.113.5'],
    ]);

    expect(ShowLauncher::run(new LauncherVisitor(ip: '203.0.113.5', remembered: true)))->toBeFalse();
});
