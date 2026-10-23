<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\VerifyTwoFactorCode;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Database\Factories\Access\EditorFactory;
use PragmaRX\Google2FA\Google2FA;

it('accepts the current code from the authenticator app', function (): void {
    $editor = Editor::factory()->withTwoFactor()->create();
    $code = (new Google2FA)->getCurrentOtp(EditorFactory::TWO_FACTOR_SECRET);

    expect(VerifyTwoFactorCode::run($editor, $code))->toBeTrue()
        ->and($editor->fresh()?->two_factor_last_used_timestep)->toBeInt();
});

it('accepts a code only once, so an overheard code cannot be replayed', function (): void {
    $editor = Editor::factory()->withTwoFactor()->create();
    $code = (new Google2FA)->getCurrentOtp(EditorFactory::TWO_FACTOR_SECRET);

    VerifyTwoFactorCode::run($editor, $code);

    expect(VerifyTwoFactorCode::run($editor->fresh() ?? $editor, $code))->toBeFalse();
});

it('rejects a wrong or malformed code', function (string $code): void {
    $editor = Editor::factory()->withTwoFactor()->create();

    expect(VerifyTwoFactorCode::run($editor, $code))->toBeFalse()
        ->and($editor->fresh()?->two_factor_last_used_timestep)->toBeNull();
})->with(['000000', '12 34', 'abcdef', '']);

it('rejects every code for an editor without a secret', function (): void {
    $editor = Editor::factory()->create();

    expect(VerifyTwoFactorCode::run($editor, '123456'))->toBeFalse();
});

it('accepts the code of the previous period within the configured window', function (): void {
    $editor = Editor::factory()->withTwoFactor()->create();
    $google2fa = new Google2FA;
    $previous = $google2fa->oathTotp(EditorFactory::TWO_FACTOR_SECRET, $google2fa->getTimestamp() - 1);

    expect(VerifyTwoFactorCode::run($editor, $previous))->toBeTrue();
});
