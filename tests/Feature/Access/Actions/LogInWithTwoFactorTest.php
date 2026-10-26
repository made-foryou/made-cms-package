<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\LogInWithTwoFactor;
use Made\CmsPackage\Access\Data\TwoFactorLogin;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\TwoFactorCodeRejected;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Database\Factories\Access\EditorFactory;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    $this->editor = Editor::factory()->withTwoFactor()->create();
});

function twoFactorLogin(int $editorId, ?string $code = null, ?string $recoveryCode = null): TwoFactorLogin
{
    return new TwoFactorLogin(editorId: $editorId, code: $code, recoveryCode: $recoveryCode, remember: false);
}

it('logs in with the code from the authenticator app', function (): void {
    $result = LogInWithTwoFactor::run(twoFactorLogin($this->editor->id, code: (new Google2FA)->getCurrentOtp(EditorFactory::TWO_FACTOR_SECRET)));

    expect($result->status)->toBe(LoginStatus::LoggedIn)
        ->and($result->method)->toBe(LoginMethod::Password)
        ->and(auth('made-cms')->user()?->is($this->editor))->toBeTrue();
});

it('logs in with a recovery code, which then stops working', function (string $typed): void {
    LogInWithTwoFactor::run(twoFactorLogin($this->editor->id, recoveryCode: $typed));
    auth('made-cms')->logout();

    expect(auth('made-cms')->check())->toBeFalse()
        ->and($this->editor->fresh()?->two_factor_recovery_codes)->toBe([hash('sha256', EditorFactory::RECOVERY_CODES[1])])
        ->and(fn () => LogInWithTwoFactor::run(twoFactorLogin($this->editor->id, recoveryCode: EditorFactory::RECOVERY_CODES[0])))
        ->toThrow(TwoFactorCodeRejected::class);
})->with([
    'as shown' => EditorFactory::RECOVERY_CODES[0],
    'with spaces and capitals' => '  '.strtoupper(EditorFactory::RECOVERY_CODES[0]).' ',
]);

it('rejects a wrong code or recovery code', function (?string $code, ?string $recoveryCode): void {
    expect(fn () => LogInWithTwoFactor::run(twoFactorLogin($this->editor->id, code: $code, recoveryCode: $recoveryCode)))
        ->toThrow(TwoFactorCodeRejected::class)
        ->and(auth('made-cms')->check())->toBeFalse();
})->with([
    'wrong code' => ['000000', null],
    'wrong recovery code' => [null, 'herstel-code-drie'],
    'nothing' => [null, null],
]);

it('rejects an editor who has no second factor or no longer exists', function (): void {
    $without = Editor::factory()->create();

    expect(fn () => LogInWithTwoFactor::run(twoFactorLogin($without->id, code: '123456')))->toThrow(TwoFactorCodeRejected::class)
        ->and(fn () => LogInWithTwoFactor::run(twoFactorLogin(999, code: '123456')))->toThrow(TwoFactorCodeRejected::class);
});
