<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Database\Factories\Access\EditorFactory;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Sleep::fake();
    $this->editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
});

function logInWithPassword(): void
{
    test()->postJson('cms/login/email', ['email' => 'menno@made-foryou.nl'])->assertOk();
    test()->postJson('cms/login/password', ['password' => EditorFactory::PASSWORD])->assertJsonPath('step', 'two_factor_offer');
}

it('starts the setup with a qr code and the key to type over', function (): void {
    logInWithPassword();

    $response = $this->postJson('cms/two-factor/setup')
        ->assertOk()
        ->assertJsonPath('step', 'two_factor_setup')
        ->assertJsonStructure(['data' => ['qr', 'secret', 'otpauth_url']]);

    expect($response->json('data.qr'))->toStartWith('<svg')
        ->and($response->json('data.secret'))->toBe($this->editor->fresh()?->two_factor_secret);
});

it('confirms the setup and shows the recovery codes', function (): void {
    logInWithPassword();
    $secret = (string) $this->postJson('cms/two-factor/setup')->json('data.secret');

    $this->postJson('cms/two-factor/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertOk()
        ->assertJsonPath('step', 'recovery_codes')
        ->assertJsonCount(8, 'data.recovery_codes');

    expect($this->editor->fresh()?->two_factor_confirmed_at)->not->toBeNull();
});

it('rejects a wrong confirmation code', function (): void {
    logInWithPassword();
    $this->postJson('cms/two-factor/setup')->assertOk();

    $this->postJson('cms/two-factor/confirm', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'Die code klopt niet. De code in je app verandert elke 30 seconden; neem de nieuwste.']);
});

it('is not available after a code login, which never typed a password', function (): void {
    $this->actingAs($this->editor, 'made-cms')
        ->postJson('cms/two-factor/setup')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('is not available to a guest', function (): void {
    $this->postJson('cms/two-factor/setup')->assertUnprocessable()->assertJsonValidationErrors(['email']);
    $this->postJson('cms/two-factor/confirm', ['code' => '123456'])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});
