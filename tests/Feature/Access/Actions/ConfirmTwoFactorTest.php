<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Actions\ConfirmTwoFactor;
use Made\CmsPackage\Access\Actions\StartTwoFactorSetup;
use Made\CmsPackage\Access\Data\TwoFactorConfirmation;
use Made\CmsPackage\Access\Data\TwoFactorSetupRequest;
use Made\CmsPackage\Access\Events\TwoFactorEnabled;
use Made\CmsPackage\Access\Exceptions\TwoFactorCodeRejected;
use Made\CmsPackage\Access\Models\Editor;
use PragmaRX\Google2FA\Google2FA;

function startSetup(Editor $editor): string
{
    return StartTwoFactorSetup::run(new TwoFactorSetupRequest($editor, passwordConfirmedAt: now()->getTimestamp()))->secret;
}

it('turns the second factor on with a code from the app and hands out recovery codes once', function (): void {
    config(['made-cms-package.two_factor.recovery_codes' => 8]);
    $editor = Editor::factory()->create();
    $secret = startSetup($editor);

    $codes = ConfirmTwoFactor::run(new TwoFactorConfirmation($editor->fresh() ?? $editor, (new Google2FA)->getCurrentOtp($secret)));

    $stored = $editor->fresh();

    expect($stored?->two_factor_confirmed_at)->not->toBeNull()
        ->and($codes->codes)->toHaveCount(8)
        ->each->toMatch('/^[a-z0-9]{10}-[a-z0-9]{10}$/')
        ->and($stored?->two_factor_recovery_codes)->toBe(array_map(fn (string $code): string => hash('sha256', $code), $codes->codes));
});

it('keeps the second factor off after a wrong code', function (): void {
    $editor = Editor::factory()->create();
    startSetup($editor);

    expect(fn () => ConfirmTwoFactor::run(new TwoFactorConfirmation($editor->fresh() ?? $editor, '000000')))
        ->toThrow(TwoFactorCodeRejected::class);

    expect($editor->fresh()?->two_factor_confirmed_at)->toBeNull();
});

it('cannot confirm without a setup in progress', function (): void {
    $editor = Editor::factory()->create();

    expect(fn () => ConfirmTwoFactor::run(new TwoFactorConfirmation($editor, '123456')))->toThrow(TwoFactorCodeRejected::class);
});

it('dispatches an event', function (): void {
    Event::fake([TwoFactorEnabled::class]);
    $editor = Editor::factory()->create();
    $secret = startSetup($editor);

    ConfirmTwoFactor::run(new TwoFactorConfirmation($editor->fresh() ?? $editor, (new Google2FA)->getCurrentOtp($secret)));

    Event::assertDispatched(TwoFactorEnabled::class, fn (TwoFactorEnabled $event): bool => $event->editor->is($editor));
});
