<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Notifications\LoginCodeNotification;
use Made\CmsPackage\Http\Cookies\EditorDevice;

beforeEach(function (): void {
    Sleep::fake();
    Notification::fake();
    Editor::factory()->create(['email' => 'menno@made-foryou.nl', 'name' => 'Menno Tempelaar']);
});

/**
 * Walks through the e-mail and code steps and returns the code from the mail.
 */
function requestCodeFor(string $email = 'menno@made-foryou.nl'): string
{
    test()->postJson('made-cms/login/email', ['email' => $email])->assertOk();
    test()->postJson('made-cms/login/code')->assertOk();

    $code = '';

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return $code;
}

it('logs the editor in and continues to the done step', function (): void {
    $code = requestCodeFor();
    $tokenBefore = session()->token();

    $response = $this->postJson('made-cms/login/code/verify', ['code' => $code]);

    $response->assertOk()
        ->assertJsonPath('step', 'done')
        ->assertJsonPath('data.editor', ['first_name' => 'Menno', 'initial' => 'M'])
        ->assertJsonPath('csrf_token', session()->token());

    expect(auth('made-cms')->check())->toBeTrue()
        ->and(auth('web')->check())->toBeFalse()
        ->and(session()->token())->not->toBe($tokenBefore)
        ->and(session('made-cms-package.login'))->toBeNull();
});

it('keeps the code of this browser working when another browser asks for a code', function (): void {
    $code = requestCodeFor();
    $browserA = session()->all();

    // Someone types the same address in another browser and asks for a code there.
    session()->flush();
    $this->postJson('made-cms/login/email', ['email' => 'menno@made-foryou.nl'])->assertOk();
    $this->postJson('made-cms/login/code')->assertOk();

    session()->flush();
    session()->put($browserA);

    $this->postJson('made-cms/login/code/verify', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('step', 'done');

    expect(auth('made-cms')->check())->toBeTrue();
});

it('remembers the device so the next login greets the editor', function (): void {
    $code = requestCodeFor();

    $cookie = collect($this->postJson('made-cms/login/code/verify', ['code' => $code])->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === EditorDevice::NAME);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getValue())->not->toContain('menno');

    auth('made-cms')->logout();

    $this->withCredentials()
        ->withUnencryptedCookie(EditorDevice::NAME, (string) $cookie->getValue())
        ->postJson('made-cms/login/email', ['email' => 'menno@made-foryou.nl'])
        ->assertJsonPath('data.name', 'Menno')
        ->assertJsonPath('data.preferred', 'code');
});

it('rejects a wrong code with one message for every reason', function (): void {
    requestCodeFor();

    $this->postJson('made-cms/login/code/verify', ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'Die code klopt niet of is verlopen. Probeer het nog eens of vraag een nieuwe aan.']);

    expect(auth('made-cms')->check())->toBeFalse();
});

it('rejects a code for an address without an account in exactly the same way', function (): void {
    $this->postJson('made-cms/login/email', ['email' => 'iemand@example.com'])->assertOk();
    $this->postJson('made-cms/login/code')->assertOk();

    $this->postJson('made-cms/login/code/verify', ['code' => '123456'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'Die code klopt niet of is verlopen. Probeer het nog eens of vraag een nieuwe aan.']);
});

it('asks for all digits', function (mixed $code): void {
    requestCodeFor();

    $this->postJson('made-cms/login/code/verify', ['code' => $code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'Vul alle cijfers van de code in.']);
})->with([null, '123', '12345a', '1234567']);

it('asks to start again when no address was entered in this browser', function (): void {
    $this->postJson('made-cms/login/code/verify', ['code' => '123456'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('limits the number of attempts', function (): void {
    requestCodeFor();

    foreach (range(1, 10) as $attempt) {
        $this->postJson('made-cms/login/code/verify', ['code' => '000000']);
    }

    $this->postJson('made-cms/login/code/verify', ['code' => '000000'])->assertTooManyRequests();
});
