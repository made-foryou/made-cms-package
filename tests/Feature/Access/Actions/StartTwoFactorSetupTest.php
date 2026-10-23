<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Access\Actions\StartTwoFactorSetup;
use Made\CmsPackage\Access\Data\TwoFactorSetupRequest;
use Made\CmsPackage\Access\Exceptions\TwoFactorSetupNotAllowed;
use Made\CmsPackage\Access\Models\Editor;

it('creates a secret that is not active until it is confirmed', function (): void {
    config(['app.name' => 'TTV Merwestad']);
    $editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    $setup = StartTwoFactorSetup::run(new TwoFactorSetupRequest($editor, passwordConfirmedAt: now()->getTimestamp()));

    $stored = $editor->fresh();

    expect($setup->secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and($stored?->two_factor_secret)->toBe($setup->secret)
        ->and($stored?->two_factor_confirmed_at)->toBeNull()
        ->and($setup->otpauthUrl)->toStartWith('otpauth://totp/TTV%20Merwestad:menno%40made-foryou.nl?secret='.$setup->secret)
        ->and($setup->otpauthUrl)->toContain('issuer=TTV%20Merwestad');
});

it('stores the secret encrypted', function (): void {
    $editor = Editor::factory()->create();

    $setup = StartTwoFactorSetup::run(new TwoFactorSetupRequest($editor, passwordConfirmedAt: now()->getTimestamp()));

    $raw = DB::table('cms_editors')->where('id', $editor->id)->value('two_factor_secret');

    expect($raw)->not->toContain($setup->secret);
});

it('starts over with a new secret when set up again before confirming', function (): void {
    $editor = Editor::factory()->create();
    $request = new TwoFactorSetupRequest($editor, passwordConfirmedAt: now()->getTimestamp());

    $first = StartTwoFactorSetup::run($request);
    $second = StartTwoFactorSetup::run($request);

    expect($second->secret)->not->toBe($first->secret);
});

it('is only allowed shortly after the password was typed', function (?int $confirmedAt): void {
    $editor = Editor::factory()->create();

    expect(fn () => StartTwoFactorSetup::run(new TwoFactorSetupRequest($editor, passwordConfirmedAt: $confirmedAt)))
        ->toThrow(TwoFactorSetupNotAllowed::class);

    expect($editor->fresh()?->two_factor_secret)->toBeNull();
})->with([
    'never' => null,
    'too long ago' => fn (): int => now()->subMinutes(16)->getTimestamp(),
]);

it('never replaces a second factor that is already confirmed', function (): void {
    $editor = Editor::factory()->withTwoFactor()->create();

    expect(fn () => StartTwoFactorSetup::run(new TwoFactorSetupRequest($editor, passwordConfirmedAt: now()->getTimestamp())))
        ->toThrow(TwoFactorSetupNotAllowed::class);
});
