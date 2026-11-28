<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\CheckBotPath;
use Made\CmsPackage\Content\Data\PagePath;

it('knows the addresses bots look for', function (string $path, bool $bots): void {
    expect(CheckBotPath::run(PagePath::fromString($path)))->toBe($bots);
})->with([
    'the login of wordpress' => ['/wp-admin', true],
    'under it' => ['/wp-admin/setup-config.php', true],
    'in capitals' => ['/WP-Login.php', true],
    'xmlrpc' => ['/xmlrpc.php', true],
    'the secrets of laravel' => ['/.env', true],
    'git' => ['/.git/config', true],
    'an address that only starts the same' => ['/wp-admin-tips', false],
    'an old page of the site' => ['/contact.html', false],
    'an old php page of the site' => ['/contact.php', false],
    'home' => ['/', false],
]);

it('takes the addresses the developer adds', function (): void {
    config(['made-cms-package.address_hits.bot_paths' => ['/Joomla', 'geen-slash', 7, '']]);

    expect(CheckBotPath::run(PagePath::fromString('/joomla/administrator')))->toBeTrue()
        ->and(CheckBotPath::run(PagePath::fromString('/geen-slash')))->toBeTrue()
        ->and(CheckBotPath::run(PagePath::fromString('/wp-admin')))->toBeTrue()
        ->and(CheckBotPath::run(PagePath::fromString('/lid-worden')))->toBeFalse();
});

it('skips an address the developer adds that can be no address, and home, which would hold every address', function (): void {
    config(['made-cms-package.address_hits.bot_paths' => ["/\u{0007}bel", '/'.str_repeat('a', 300), '/', '//']]);

    expect(CheckBotPath::run(PagePath::fromString('/lid-worden')))->toBeFalse()
        ->and(CheckBotPath::run(PagePath::fromString('/')))->toBeFalse();
});
