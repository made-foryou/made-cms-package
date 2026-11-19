<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\IssueLoginCode;
use Made\CmsPackage\Access\Actions\LogInWithLink;
use Made\CmsPackage\Access\Data\LinkLogin;
use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\LoginLinkRejected;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Models\LoginCode;
use Made\CmsPackage\Database\Factories\Access\LoginCodeFactory;

beforeEach(function (): void {
    $this->editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
});

function loginCodeWithLink(array $attributes = [], string $factoryState = ''): LoginCode
{
    $factory = LoginCode::factory();

    if ($factoryState !== '') {
        $factory = $factory->{$factoryState}();
    }

    return $factory->create(['email' => 'menno@made-foryou.nl', 'link_hash' => hash('sha256', 'the-link-token'), ...$attributes]);
}

it('logs in the browser that asked for the code and returns to its page', function (): void {
    $loginCode = loginCodeWithLink(['return_to' => '/over-ons']);

    $login = LogInWithLink::run(new LinkLogin(token: 'the-link-token', binding: LoginCodeFactory::BINDING));

    expect($login->result->status)->toBe(LoginStatus::LoggedIn)
        ->and($login->result->method)->toBe(LoginMethod::Code)
        ->and($login->returnTo)->toBe('/over-ons')
        ->and(auth('made-cms')->user()?->is($this->editor))->toBeTrue()
        ->and($loginCode->fresh()?->consumed_at)->not->toBeNull();
});

it('refuses another browser, so a link sent to someone else never logs in the sender', function (?string $binding): void {
    $loginCode = loginCodeWithLink();

    expect(fn () => LogInWithLink::run(new LinkLogin(token: 'the-link-token', binding: $binding)))
        ->toThrow(LoginLinkRejected::class, 'another browser');

    expect(auth('made-cms')->check())->toBeFalse()
        ->and($loginCode->fresh()?->consumed_at)->toBeNull();
})->with(['other browser' => 'other-binding', 'no login in progress' => null]);

it('refuses an expired, used or unknown link', function (string $state, string $token): void {
    loginCodeWithLink(factoryState: $state);

    expect(fn () => LogInWithLink::run(new LinkLogin(token: $token, binding: LoginCodeFactory::BINDING)))
        ->toThrow(LoginLinkRejected::class, 'no longer works');
})->with([
    'expired' => ['expired', 'the-link-token'],
    'used' => ['consumed', 'the-link-token'],
    'unknown' => ['', 'another-token'],
]);

it('never logs in an editor whose address differs from the one the link went to only in an accent', function (): void {
    Editor::factory()->create(['email' => 'jose@example.com']);
    loginCodeWithLink(['email' => 'jose@exämple.com']);

    expect(fn () => LogInWithLink::run(new LinkLogin(token: 'the-link-token', binding: LoginCodeFactory::BINDING)))
        ->toThrow(LoginLinkRejected::class)
        ->and(auth('made-cms')->check())->toBeFalse();
});

it('works only once', function (): void {
    loginCodeWithLink();
    LogInWithLink::run(new LinkLogin(token: 'the-link-token', binding: LoginCodeFactory::BINDING));
    auth('made-cms')->logout();

    expect(fn () => LogInWithLink::run(new LinkLogin(token: 'the-link-token', binding: LoginCodeFactory::BINDING)))
        ->toThrow(LoginLinkRejected::class);
});

it('still logs in with the link after another browser asked for a code', function (): void {
    $browserA = IssueLoginCode::run(new LoginCodeRequest(email: 'menno@made-foryou.nl', binding: 'browser-a', returnTo: null));
    IssueLoginCode::run(new LoginCodeRequest(email: 'menno@made-foryou.nl', binding: 'browser-b', returnTo: null));

    $login = LogInWithLink::run(new LinkLogin(token: $browserA->linkToken, binding: 'browser-a'));

    expect($login->result->status)->toBe(LoginStatus::LoggedIn)
        ->and(auth('made-cms')->user()?->is($this->editor))->toBeTrue();
});
