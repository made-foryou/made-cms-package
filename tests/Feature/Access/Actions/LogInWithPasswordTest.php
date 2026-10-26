<?php

declare(strict_types=1);

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Actions\LogInWithPassword;
use Made\CmsPackage\Access\Data\PasswordLogin;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\CredentialsRejected;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Database\Factories\Access\EditorFactory;

beforeEach(function (): void {
    Sleep::fake();
});

function passwordLogin(string $password = EditorFactory::PASSWORD, string $email = 'menno@made-foryou.nl', bool $remember = false): PasswordLogin
{
    return new PasswordLogin(email: $email, password: $password, remember: $remember);
}

it('logs the editor in and offers a second factor', function (): void {
    $editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    $result = LogInWithPassword::run(passwordLogin());

    expect($result->status)->toBe(LoginStatus::TwoFactorOffered)
        ->and($result->method)->toBe(LoginMethod::Password)
        ->and($result->editor->is($editor))->toBeTrue()
        ->and(auth('made-cms')->user()?->is($editor))->toBeTrue();
});

it('asks for the second factor before logging in an editor who turned it on', function (): void {
    $editor = Editor::factory()->withTwoFactor()->create(['email' => 'menno@made-foryou.nl']);

    $result = LogInWithPassword::run(passwordLogin());

    expect($result->status)->toBe(LoginStatus::TwoFactorRequired)
        ->and($result->editor->is($editor))->toBeTrue()
        ->and(auth('made-cms')->check())->toBeFalse();
});

it('remembers the editor when asked', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    LogInWithPassword::run(passwordLogin(remember: true));

    /** @var SessionGuard $guard */
    $guard = auth('made-cms');

    expect($guard->getCookieJar()->hasQueued($guard->getRecallerName()))->toBeTrue();
});

it('rejects a wrong password, an unknown address and an account without a password alike', function (string $email, string $password): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    Editor::factory()->withoutPassword()->create(['email' => 'code@made-foryou.nl']);

    expect(fn () => LogInWithPassword::run(passwordLogin(password: $password, email: $email)))->toThrow(CredentialsRejected::class)
        ->and(auth('made-cms')->check())->toBeFalse();

    Sleep::assertSleptTimes(1);
})->with([
    'wrong password' => ['menno@made-foryou.nl', 'verkeerd'],
    'unknown address' => ['iemand@example.com', EditorFactory::PASSWORD],
    'no password' => ['code@made-foryou.nl', ''],
]);

it('rehashes an outdated password hash after a successful login', function (): void {
    $editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    $editor->forceFill(['password' => Hash::driver('bcrypt')->make(EditorFactory::PASSWORD, ['rounds' => 4])])->saveQuietly();
    config(['hashing.bcrypt.rounds' => 5]);
    Hash::forgetDrivers();

    LogInWithPassword::run(passwordLogin());

    expect(Hash::needsRehash((string) $editor->fresh()?->password))->toBeFalse()
        ->and(Hash::check(EditorFactory::PASSWORD, (string) $editor->fresh()?->password))->toBeTrue();
});
