<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Database\Factories\Access\EditorFactory;
use Made\CmsPackage\Http\Cookies\EditorDevice;

beforeEach(function (): void {
    Sleep::fake();
});

function enterEmail(string $email = 'menno@made-foryou.nl'): void
{
    test()->postJson('made-cms/login/email', ['email' => $email])->assertOk();
}

it('logs the editor in and offers to set up a second factor', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl', 'name' => 'Menno']);
    enterEmail();

    $this->postJson('made-cms/login/password', ['password' => EditorFactory::PASSWORD, 'remember' => true])
        ->assertOk()
        ->assertJsonPath('step', 'two_factor_offer')
        ->assertJsonPath('data.editor.first_name', 'Menno')
        ->assertCookie(EditorDevice::NAME);

    expect(auth('made-cms')->check())->toBeTrue()
        ->and(session('made-cms-package.password_confirmed_at'))->toBeInt();
});

it('does not remember the device when the editor unticks the box', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    enterEmail();

    $this->postJson('made-cms/login/password', ['password' => EditorFactory::PASSWORD, 'remember' => false])
        ->assertOk()
        ->assertCookieMissing(EditorDevice::NAME);
});

it('continues to the second factor without logging in yet', function (): void {
    $editor = Editor::factory()->withTwoFactor()->create(['email' => 'menno@made-foryou.nl']);
    enterEmail();
    $sessionId = session()->getId();

    $this->postJson('made-cms/login/password', ['password' => EditorFactory::PASSWORD, 'remember' => true])
        ->assertOk()
        ->assertJsonPath('step', 'two_factor')
        ->assertJsonPath('data', []);

    expect(auth('made-cms')->check())->toBeFalse()
        ->and(session('made-cms-package.two_factor.editor'))->toBe($editor->id)
        ->and(session()->getId())->not->toBe($sessionId);
});

it('rejects a wrong password with a translated message', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    enterEmail();

    $this->postJson('made-cms/login/password', ['password' => 'verkeerd'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'Dat wachtwoord klopt niet. Probeer het nog eens, of stuur jezelf een code.']);
});

it('answers an unknown address exactly like a wrong password', function (): void {
    enterEmail('iemand@example.com');

    $this->postJson('made-cms/login/password', ['password' => 'verkeerd'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'Dat wachtwoord klopt niet. Probeer het nog eens, of stuur jezelf een code.']);
});

it('asks for a password', function (): void {
    enterEmail();

    $this->postJson('made-cms/login/password', ['password' => ''])
        ->assertJsonValidationErrors(['password' => 'Vul je wachtwoord in.']);
});

it('limits the number of attempts', function (): void {
    enterEmail();

    foreach (range(1, 10) as $attempt) {
        $this->postJson('made-cms/login/password', ['password' => 'verkeerd']);
    }

    $this->postJson('made-cms/login/password', ['password' => 'verkeerd'])->assertTooManyRequests();
});
