<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Database\Factories\Access\EditorFactory;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Sleep::fake();
    $this->editor = Editor::factory()->withTwoFactor()->create(['email' => 'menno@made-foryou.nl', 'name' => 'Menno']);
});

function passwordThenTwoFactor(bool $remember = true): void
{
    test()->postJson('cms/login/email', ['email' => 'menno@made-foryou.nl'])->assertOk();
    test()->postJson('cms/login/password', ['password' => EditorFactory::PASSWORD, 'remember' => $remember])->assertJsonPath('step', 'two_factor');
}

it('logs in after the code from the app', function (): void {
    passwordThenTwoFactor();

    $this->postJson('cms/login/two-factor', ['code' => (new Google2FA)->getCurrentOtp(EditorFactory::TWO_FACTOR_SECRET)])
        ->assertOk()
        ->assertJsonPath('step', 'done')
        ->assertJsonPath('data.editor.first_name', 'Menno')
        ->assertCookie(EditorDevice::NAME);

    expect(auth('made-cms')->check())->toBeTrue()
        ->and(session('made-cms-package.two_factor'))->toBeNull();
});

it('keeps the choice not to remember the device', function (): void {
    passwordThenTwoFactor(remember: false);

    $this->postJson('cms/login/two-factor', ['code' => (new Google2FA)->getCurrentOtp(EditorFactory::TWO_FACTOR_SECRET)])
        ->assertOk()
        ->assertCookieMissing(EditorDevice::NAME);
});

it('logs in with a recovery code', function (): void {
    passwordThenTwoFactor();

    $this->postJson('cms/login/two-factor', ['recovery_code' => EditorFactory::RECOVERY_CODES[0]])
        ->assertOk()
        ->assertJsonPath('step', 'done');
});

it('rejects a wrong code and a wrong recovery code', function (): void {
    passwordThenTwoFactor();

    $this->postJson('cms/login/two-factor', ['code' => '000000'])
        ->assertJsonValidationErrors(['code' => 'Die code klopt niet. De code in je app verandert elke 30 seconden; neem de nieuwste.']);

    $this->postJson('cms/login/two-factor', ['recovery_code' => 'iets-anders'])
        ->assertJsonValidationErrors(['recovery_code' => 'Die herstelcode klopt niet of is al gebruikt.']);

    expect(auth('made-cms')->check())->toBeFalse();
});

it('needs the password step first', function (): void {
    $this->postJson('cms/login/two-factor', ['code' => (new Google2FA)->getCurrentOtp(EditorFactory::TWO_FACTOR_SECRET)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(auth('made-cms')->check())->toBeFalse();
});

it('expires ten minutes after the password', function (): void {
    passwordThenTwoFactor();
    $this->travel(11)->minutes();

    $this->postJson('cms/login/two-factor', ['code' => (new Google2FA)->getCurrentOtp(EditorFactory::TWO_FACTOR_SECRET)])
        ->assertJsonValidationErrors(['email']);
});

it('limits the number of attempts', function (): void {
    passwordThenTwoFactor();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('cms/login/two-factor', ['code' => '000000']);
    }

    $this->postJson('cms/login/two-factor', ['code' => '000000'])->assertTooManyRequests();
});
