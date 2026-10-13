<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Builders\LoginCodeBuilder;
use Made\CmsPackage\Access\Models\LoginCode;

it('uses the custom builder for login code queries', function (): void {
    expect(LoginCode::query())->toBeInstanceOf(LoginCodeBuilder::class);
});

it('only returns codes that are neither expired nor consumed as active', function (): void {
    $active = LoginCode::factory()->create();
    LoginCode::factory()->expired()->create();
    LoginCode::factory()->consumed()->create();

    expect(LoginCode::query()->active()->pluck('id')->all())->toBe([$active->id]);
});

it('treats a code expiring exactly now as inactive', function (): void {
    $this->freezeTime();

    LoginCode::factory()->create(['expires_at' => now()]);

    expect(LoginCode::query()->active()->exists())->toBeFalse();
});

it('filters codes by e-mail address', function (): void {
    $mine = LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);
    LoginCode::factory()->create(['email' => 'iemand@anders.nl']);

    expect(LoginCode::query()->forEmail('menno@made-foryou.nl')->pluck('id')->all())->toBe([$mine->id]);
});

it('chains the e-mail filter with the active filter', function (): void {
    $active = LoginCode::factory()->create(['email' => 'menno@made-foryou.nl']);
    LoginCode::factory()->expired()->create(['email' => 'menno@made-foryou.nl']);
    LoginCode::factory()->create(['email' => 'iemand@anders.nl']);

    expect(LoginCode::query()->forEmail('menno@made-foryou.nl')->active()->pluck('id')->all())->toBe([$active->id]);
});
