<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Actions\RequestLoginCode;
use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Access\Events\LoginCodeIssued;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Models\LoginCode;

beforeEach(function (): void {
    Sleep::fake();
    Event::fake([LoginCodeIssued::class]);
});

it('issues a code for an editor', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    RequestLoginCode::run(new LoginCodeRequest(email: 'Menno@made-foryou.nl', binding: 'binding', returnTo: null));

    expect(LoginCode::query()->forEmail('menno@made-foryou.nl')->active()->count())->toBe(1);
    Event::assertDispatched(LoginCodeIssued::class);
});

it('silently issues nothing for an address without an account', function (): void {
    RequestLoginCode::run(new LoginCodeRequest(email: 'iemand@example.com', binding: 'binding', returnTo: null));

    expect(LoginCode::query()->count())->toBe(0);
    Event::assertNotDispatched(LoginCodeIssued::class);
});

it('issues nothing for an address that differs from an editor\'s only in an accent', function (): void {
    Editor::factory()->create(['email' => 'jose@example.com']);

    RequestLoginCode::run(new LoginCodeRequest(email: 'jose@exämple.com', binding: 'binding', returnTo: null));

    expect(LoginCode::query()->count())->toBe(0);
    Event::assertNotDispatched(LoginCodeIssued::class);
});

it('takes at least the same time for known and unknown addresses', function (): void {
    RequestLoginCode::run(new LoginCodeRequest(email: 'iemand@example.com', binding: 'binding', returnTo: null));

    Sleep::assertSleptTimes(1);
});
