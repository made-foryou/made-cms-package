<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Made\CmsPackage\Access\Actions\RememberDevice;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Cookies\EditorDevice;

function postLoginEmail(string $email): TestResponse
{
    return test()->postJson('made-cms/login/email', ['email' => $email]);
}

it('continues to the method step for a known address', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl', 'name' => 'Menno Tempelaar']);

    postLoginEmail('menno@made-foryou.nl')
        ->assertOk()
        ->assertJsonPath('step', 'method')
        ->assertJsonPath('data.email', 'menno@made-foryou.nl')
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.preferred', null)
        ->assertJsonPath('data.methods', ['code', 'password'])
        ->assertJsonStructure(['step', 'data', 'csrf_token']);
});

it('answers an unknown address exactly like a known one', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    $known = postLoginEmail('menno@made-foryou.nl')->json();
    $unknown = postLoginEmail('iemand@example.com')->json();

    unset($known['csrf_token'], $unknown['csrf_token'], $known['data']['email'], $unknown['data']['email']);

    expect($unknown)->toBe($known);
});

it('greets the editor on a device that logged in with this address before', function (): void {
    $editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl', 'name' => 'Menno Tempelaar']);

    $this->withCredentials()
        ->withCookie(EditorDevice::NAME, EditorDevice::value(RememberDevice::run($editor, LoginMethod::Password)))
        ->postJson('made-cms/login/email', ['email' => 'Menno@made-foryou.nl'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Menno')
        ->assertJsonPath('data.preferred', 'password');
});

it('does not greet anyone with the device cookie of another address', function (): void {
    $editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    $this->withCredentials()
        ->withCookie(EditorDevice::NAME, EditorDevice::value(RememberDevice::run($editor, LoginMethod::Code)))
        ->postJson('made-cms/login/email', ['email' => 'iemand@example.com'])
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.preferred', null);
});

it('remembers the address for the next steps', function (): void {
    postLoginEmail(' Menno@Made-ForYou.nl ')->assertOk();

    expect(session('made-cms-package.login.email'))->toBe('menno@made-foryou.nl')
        ->and(session('made-cms-package.login.binding'))->toBeString()->toHaveLength(40);
});

it('starts a new binding for every address that is entered', function (): void {
    postLoginEmail('menno@made-foryou.nl');
    $first = session('made-cms-package.login.binding');

    postLoginEmail('menno@made-foryou.nl');

    expect(session('made-cms-package.login.binding'))->not->toBe($first);
});

it('rejects an invalid address with a translated message', function (array $payload, string $message): void {
    $this->postJson('made-cms/login/email', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => $message]);
})->with([
    'missing' => [[], 'Vul je e-mailadres in.'],
    'not an address' => [['email' => 'menno'], 'Dat lijkt geen e-mailadres. Staat er een @ in?'],
]);

it('rejects in the configured launcher language', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);

    $this->postJson('made-cms/login/email', [])
        ->assertJsonValidationErrors(['email' => 'Fill in your e-mail address.']);
});
