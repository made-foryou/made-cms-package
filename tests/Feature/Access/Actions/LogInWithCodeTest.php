<?php

declare(strict_types=1);

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Actions\LogInWithCode;
use Made\CmsPackage\Access\Data\CodeLogin;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\LoginCodeRejected;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Models\LoginCode;
use Made\CmsPackage\Database\Factories\Access\LoginCodeFactory;

beforeEach(function (): void {
    Sleep::fake();
    $this->editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
});

function codeLogin(string $code = LoginCodeFactory::CODE, string $binding = LoginCodeFactory::BINDING, string $email = 'menno@made-foryou.nl'): CodeLogin
{
    return new CodeLogin(email: $email, code: $code, binding: $binding);
}

it('logs the editor in with the code from the mail', function (): void {
    $loginCode = LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);

    $result = LogInWithCode::run(codeLogin());

    expect($result->status)->toBe(LoginStatus::LoggedIn)
        ->and($result->method)->toBe(LoginMethod::Code)
        ->and($result->editor->is($this->editor))->toBeTrue()
        ->and(auth('made-cms')->user()?->is($this->editor))->toBeTrue()
        ->and($loginCode->fresh()?->consumed_at)->not->toBeNull();
});

it('keeps the editor logged in after a code login', function (): void {
    LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);

    LogInWithCode::run(codeLogin());

    /** @var SessionGuard $guard */
    $guard = auth('made-cms');

    expect($guard->getCookieJar()->hasQueued($guard->getRecallerName()))->toBeTrue();
});

it('works only once', function (): void {
    LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);
    LogInWithCode::run(codeLogin());
    auth('made-cms')->logout();

    expect(fn () => LogInWithCode::run(codeLogin()))->toThrow(LoginCodeRejected::class);
});

it('rejects a wrong code and counts the attempt', function (): void {
    $loginCode = LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);

    expect(fn () => LogInWithCode::run(codeLogin(code: '000000')))->toThrow(LoginCodeRejected::class)
        ->and($loginCode->fresh()?->attempts)->toBe(1)
        ->and(auth('made-cms')->check())->toBeFalse();
});

it('invalidates the code after the maximum number of attempts, even for the right code', function (): void {
    config(['made-cms-package.login_code.max_attempts' => 3]);
    $loginCode = LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);

    foreach (range(1, 3) as $attempt) {
        try {
            LogInWithCode::run(codeLogin(code: '000000'));
        } catch (LoginCodeRejected) {
        }
    }

    expect($loginCode->fresh()?->consumed_at)->not->toBeNull()
        ->and(fn () => LogInWithCode::run(codeLogin()))->toThrow(LoginCodeRejected::class);
});

it('rejects the code in another browser', function (): void {
    LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);

    expect(fn () => LogInWithCode::run(codeLogin(binding: 'other-browser')))->toThrow(LoginCodeRejected::class)
        ->and(auth('made-cms')->check())->toBeFalse();
});

it('rejects an expired or used code', function (string $state): void {
    LoginCode::factory()->{$state}()->create(['email' => 'menno@made-foryou.nl']);

    expect(fn () => LogInWithCode::run(codeLogin()))->toThrow(LoginCodeRejected::class);
})->with(['expired', 'consumed']);

it('rejects the code of another address', function (): void {
    LoginCode::factory()->create(['email' => 'iemand@example.com']);

    expect(fn () => LogInWithCode::run(codeLogin()))->toThrow(LoginCodeRejected::class);
});

it('rejects a code when the account was removed in the meantime', function (): void {
    LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);
    $this->editor->delete();

    expect(fn () => LogInWithCode::run(codeLogin()))->toThrow(LoginCodeRejected::class)
        ->and(auth('made-cms')->check())->toBeFalse();
});

it('checks the code without the slow password hasher', function (string $code): void {
    LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);
    $hasher = countHashing();

    rescue(fn () => LogInWithCode::run(codeLogin(code: $code)), report: false);

    expect($hasher->checks)->toBe(0);
})->with([LoginCodeFactory::CODE, '654321']);

it('takes the same minimum time for every attempt', function (): void {
    expect(fn () => LogInWithCode::run(codeLogin(email: 'iemand@example.com')))->toThrow(LoginCodeRejected::class);

    Sleep::assertSleptTimes(1);
});
