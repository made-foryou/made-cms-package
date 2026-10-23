<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Made\CmsPackage\Access\Actions\IssueLoginCode;
use Made\CmsPackage\Access\Data\IssuedLoginCode;
use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Access\Events\LoginCodeIssued;
use Made\CmsPackage\Access\Models\LoginCode;

function loginCodeRequest(string $email = 'menno@made-foryou.nl', string $binding = 'browser-binding', ?string $returnTo = null): LoginCodeRequest
{
    return new LoginCodeRequest(email: $email, binding: $binding, returnTo: $returnTo);
}

it('stores a login code for the e-mail address', function (): void {
    $issued = IssueLoginCode::run(loginCodeRequest());

    expect($issued)->toBeInstanceOf(IssuedLoginCode::class)
        ->and($issued->loginCode->exists)->toBeTrue()
        ->and($issued->loginCode->email)->toBe('menno@made-foryou.nl')
        ->and(LoginCode::query()->forEmail('menno@made-foryou.nl')->active()->count())->toBe(1);
});

it('only stores hashes of the plain code, the link token and the browser binding', function (): void {
    $issued = IssueLoginCode::run(loginCodeRequest(binding: 'browser-binding'));

    $stored = LoginCode::query()->findOrFail($issued->loginCode->id);

    expect($stored->code_hash)->not->toBe($issued->code)
        ->and(Hash::check($issued->code, $stored->code_hash))->toBeTrue()
        ->and($issued->linkToken)->toHaveLength(64)
        ->and($stored->link_hash)->toBe(hash('sha256', $issued->linkToken))
        ->and($stored->session_hash)->toBe(hash('sha256', 'browser-binding'))
        ->and($stored->attempts)->toBe(0);
});

it('issues a numeric code with the configured length', function (int $length): void {
    config(['made-cms-package.login_code.length' => $length]);

    $code = IssueLoginCode::run(loginCodeRequest())->code;

    expect($code)->toHaveLength($length)
        ->toMatch('/^\d+$/');
})->with([6, 8]);

it('expires the code after the configured number of minutes', function (): void {
    $this->freezeSecond();
    config(['made-cms-package.login_code.ttl_minutes' => 15]);

    $issued = IssueLoginCode::run(loginCodeRequest());

    expect($issued->loginCode->expires_at->equalTo(now()->addMinutes(15)))->toBeTrue();
});

it('keeps only the newest code of an address active', function (): void {
    $first = IssueLoginCode::run(loginCodeRequest());
    $other = IssueLoginCode::run(loginCodeRequest(email: 'iemand@example.com'));
    $second = IssueLoginCode::run(loginCodeRequest());

    expect(LoginCode::query()->forEmail('menno@made-foryou.nl')->active()->sole()->is($second->loginCode))->toBeTrue()
        ->and($first->loginCode->fresh()?->consumed_at)->not->toBeNull()
        ->and($other->loginCode->fresh()?->consumed_at)->toBeNull();
});

it('stores the page to return to after the mail link', function (): void {
    $issued = IssueLoginCode::run(loginCodeRequest(returnTo: '/over-ons'));

    expect($issued->loginCode->return_to)->toBe('/over-ons');
});

it('dispatches an event for the issued code', function (): void {
    Event::fake([LoginCodeIssued::class]);

    $issued = IssueLoginCode::run(loginCodeRequest());

    Event::assertDispatched(
        LoginCodeIssued::class,
        fn (LoginCodeIssued $event): bool => $event->issued->loginCode->is($issued->loginCode)
            && $event->issued->code === $issued->code
            && $event->issued->linkToken === $issued->linkToken,
    );
});
