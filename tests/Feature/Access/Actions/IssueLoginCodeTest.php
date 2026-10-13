<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Made\CmsPackage\Access\Actions\IssueLoginCode;
use Made\CmsPackage\Access\Data\IssuedLoginCode;
use Made\CmsPackage\Access\Events\LoginCodeIssued;
use Made\CmsPackage\Access\Models\LoginCode;

it('stores a login code for the e-mail address', function (): void {
    $issued = IssueLoginCode::run('menno@made-foryou.nl');

    expect($issued)->toBeInstanceOf(IssuedLoginCode::class)
        ->and($issued->loginCode->exists)->toBeTrue()
        ->and($issued->loginCode->email)->toBe('menno@made-foryou.nl')
        ->and(LoginCode::query()->forEmail('menno@made-foryou.nl')->active()->count())->toBe(1);
});

it('only stores a hash of the plain code', function (): void {
    $issued = IssueLoginCode::run('menno@made-foryou.nl');

    $stored = LoginCode::query()->findOrFail($issued->loginCode->id);

    expect($stored->code_hash)->not->toBe($issued->code)
        ->and(Hash::check($issued->code, $stored->code_hash))->toBeTrue();
});

it('issues a numeric code with the configured length', function (int $length): void {
    config(['made-cms-package.login_code.length' => $length]);

    $code = IssueLoginCode::run('menno@made-foryou.nl')->code;

    expect($code)->toHaveLength($length)
        ->toMatch('/^\d+$/');
})->with([6, 8]);

it('expires the code after the configured number of minutes', function (): void {
    $this->freezeSecond();
    config(['made-cms-package.login_code.ttl_minutes' => 15]);

    $issued = IssueLoginCode::run('menno@made-foryou.nl');

    expect($issued->loginCode->expires_at->equalTo(now()->addMinutes(15)))->toBeTrue();
});

it('dispatches an event for the issued code', function (): void {
    Event::fake([LoginCodeIssued::class]);

    $issued = IssueLoginCode::run('menno@made-foryou.nl');

    Event::assertDispatched(
        LoginCodeIssued::class,
        fn (LoginCodeIssued $event): bool => $event->issued->loginCode->is($issued->loginCode)
            && $event->issued->code === $issued->code,
    );
});
