<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Models\LoginCode;
use Made\CmsPackage\Access\Notifications\LoginCodeNotification;

beforeEach(function (): void {
    Sleep::fake();
    Notification::fake();
});

function startLogin(string $email = 'menno@made-foryou.nl'): void
{
    test()->postJson('made-cms/login/email', ['email' => $email])->assertOk();
}

it('sends a code to an editor and continues to the code step', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    startLogin();

    $this->postJson('made-cms/login/code')
        ->assertOk()
        ->assertJsonPath('step', 'code')
        ->assertJsonPath('data.email', 'menno@made-foryou.nl')
        ->assertJsonPath('data.minutes', 10);

    expect(LoginCode::query()->forEmail('menno@made-foryou.nl')->active()->sole()->session_hash)
        ->toBe(hash('sha256', (string) session('made-cms-package.login.binding')));

    Notification::assertSentOnDemandTimes(LoginCodeNotification::class, 1);
});

it('tells the mail from which browser the code was asked for', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    startLogin();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Version/18.0 Safari/605.1.15')
        ->postJson('made-cms/login/code')
        ->assertOk();

    Notification::assertSentOnDemand(
        LoginCodeNotification::class,
        fn (LoginCodeNotification $notification): bool => str_contains((string) $notification->userAgent, 'Macintosh'),
    );
});

it('answers an address without an account exactly the same', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    startLogin('menno@made-foryou.nl');
    $known = $this->postJson('made-cms/login/code')->json();

    startLogin('iemand@example.com');
    $unknown = $this->postJson('made-cms/login/code')->json();

    unset($known['csrf_token'], $unknown['csrf_token'], $known['data']['email'], $unknown['data']['email']);

    expect($unknown)->toBe($known);
    Notification::assertSentOnDemandTimes(LoginCodeNotification::class, 1);
});

it('remembers a relative page to return to', function (string $returnTo, ?string $stored): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    startLogin();

    $this->postJson('made-cms/login/code', ['return_to' => $returnTo])->assertOk();

    expect(LoginCode::query()->sole()->return_to)->toBe($stored);
})->with([
    'a page' => ['/over-ons?tab=2', '/over-ons?tab=2'],
    'another site' => ['https://evil.example/', null],
    'protocol relative' => ['//evil.example/', null],
    'backslash' => ['/\\evil.example/', null],
]);

it('asks to start again when no address was entered in this browser', function (): void {
    $this->postJson('made-cms/login/code')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'Je sessie is verlopen. Begin opnieuw met je e-mailadres.']);
});

it('limits the number of codes per address', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    startLogin();

    foreach (range(1, 3) as $attempt) {
        $this->postJson('made-cms/login/code')->assertOk();
    }

    $this->postJson('made-cms/login/code')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonStructure(['message', 'retry_after']);

    Notification::assertSentOnDemandTimes(LoginCodeNotification::class, 3);
});

/**
 * Asks for a code from this ip address.
 */
function askCodeFrom(string $ip): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('made-cms/login/code');
}

it('counts the ip addresses of one IPv6 /64 as one network', function (): void {
    startLogin();

    askCodeFrom('2001:db8:1:2::1')->assertOk();
    askCodeFrom('2001:db8:1:2::2')->assertOk();
    askCodeFrom('2001:db8:1:2:ffff:ffff:ffff:ffff')->assertOk();

    askCodeFrom('2001:db8:1:2::99')->assertTooManyRequests();
    askCodeFrom('2001:db8:1:3::1')->assertOk();
});

it('keeps IPv4 addresses apart, also when they are written as IPv6', function (): void {
    startLogin();

    foreach (range(1, 3) as $attempt) {
        askCodeFrom('::ffff:203.0.113.5')->assertOk();
    }

    askCodeFrom('::ffff:203.0.113.5')->assertTooManyRequests();
    askCodeFrom('::ffff:198.51.100.20')->assertOk();
    askCodeFrom('203.0.113.6')->assertOk();
});
