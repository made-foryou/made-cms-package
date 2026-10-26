<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\RememberDevice;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Models\Editor;

it('remembers the first name and the method, but never the plain e-mail address', function (): void {
    $editor = Editor::factory()->make(['name' => 'Menno Tempelaar', 'email' => 'menno@made-foryou.nl']);

    $device = RememberDevice::run($editor, LoginMethod::Code);

    expect($device->firstName)->toBe('Menno')
        ->and($device->method)->toBe(LoginMethod::Code)
        ->and($device->emailHash)->not->toContain('menno')
        ->and($device->emailHash)->toBe(RememberDevice::make()->emailHash(' MENNO@made-foryou.nl'));
});

it('hashes the address with the application key', function (): void {
    $hash = RememberDevice::make()->emailHash('menno@made-foryou.nl');

    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);

    expect(RememberDevice::make()->emailHash('menno@made-foryou.nl'))->not->toBe($hash);
});
