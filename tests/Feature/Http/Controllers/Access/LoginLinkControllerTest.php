<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Models\LoginCode;
use Made\CmsPackage\Access\Notifications\LoginCodeNotification;
use Made\CmsPackage\Http\Cookies\EditorDevice;

beforeEach(function (): void {
    Sleep::fake();
    Notification::fake();
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
});

/**
 * Asks for a code in this browser and returns the path of the link in the mail.
 */
function requestLinkFor(?string $returnTo = null): string
{
    test()->postJson('cms/login/email', ['email' => 'menno@made-foryou.nl'])->assertOk();
    test()->postJson('cms/login/code', ['return_to' => $returnTo])->assertOk();

    $url = '';

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$url): bool {
        $url = $notification->url;

        return true;
    });

    return (string) parse_url($url, PHP_URL_PATH);
}

it('only shows a confirmation when the link is opened, so a mail scanner cannot use it up', function (): void {
    $link = requestLinkFor();

    $this->get($link)
        ->assertOk()
        ->assertSee('Inloggen bij TTV Merwestad')
        ->assertSee('<form method="post" action="'.$link.'"', false)
        ->assertHeader('X-Frame-Options', 'DENY');

    expect(auth('made-cms')->check())->toBeFalse()
        ->and(LoginCode::query()->active()->count())->toBe(1);
});

it('logs in and returns to the page where the code was asked for', function (): void {
    $link = requestLinkFor('/over-ons');

    $this->post($link)
        ->assertRedirect('/over-ons')
        ->assertSessionHas('made-cms-package.arrived', true)
        ->assertCookie(EditorDevice::NAME);

    expect(auth('made-cms')->check())->toBeTrue()
        ->and(session('made-cms-package.login'))->toBeNull();
});

it('lets the launcher on that page know the editor just arrived', function (): void {
    Route::middleware('web')->get('over-ons', fn (): string => '<html><head><title>Over ons</title></head><body></body></html>');

    $link = requestLinkFor('/over-ons');

    $this->followingRedirects()
        ->post($link)
        ->assertOk()
        ->assertSee('data-view="edit_bar"', false)
        ->assertSee('data-arrived', false);

    $this->get('over-ons')->assertDontSee('data-arrived', false);
});

it('returns to the home page without a page to return to', function (): void {
    $this->post(requestLinkFor())->assertRedirect('/');
});

it('explains that the link only works in the browser that asked for the code', function (): void {
    $link = requestLinkFor();
    $this->flushSession();

    $this->post($link)
        ->assertForbidden()
        ->assertSee('Deze link werkt alleen in de browser waarin je de code aanvroeg.');

    expect(auth('made-cms')->check())->toBeFalse()
        ->and(LoginCode::query()->active()->count())->toBe(1);
});

it('explains that a used or expired link no longer works', function (): void {
    $link = requestLinkFor();
    $this->post($link)->assertRedirect();
    auth('made-cms')->logout();

    $this->post($link)
        ->assertStatus(410)
        ->assertSee('Deze link werkt niet meer.');
});
