<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Notifications\LoginCodeNotification;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
});

it('serves the login page on the cms prefix itself', function (): void {
    expect(route('made-cms-package.login', [], false))->toBe('/made-cms');

    $this->get('made-cms')
        ->assertOk()
        ->assertSee('<title>Inloggen bij TTV Merwestad</title>', false)
        ->assertSee('data-view="login"', false)
        ->assertSee('data-start="email"', false)
        ->assertSee('<template data-step="email">', false)
        ->assertSee('<template data-step="continue">', false)
        ->assertSee('"session":"\/made-cms\/session"', false)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('is never stored by a cache', function (): void {
    expect($this->get('made-cms')->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('opens for every visitor, also without the launcher tile', function (): void {
    config(['made-cms-package.launcher.enabled' => false, 'made-cms-package.launcher.allowed_ips' => []]);

    $response = $this->get('made-cms')->assertOk()->assertSee('data-start="email"', false);

    // The page is the launcher's own markup; no tile is injected on top of it.
    expect(substr_count((string) $response->getContent(), '<made-cms-launcher'))->toBe(1);
});

it('uses the page texts of the design', function (): void {
    $this->get('made-cms')
        ->assertSee('Welkom terug.')
        ->assertSee('We halen op waar je gebleven was.')
        ->assertSee('Verder waar je was?')
        ->assertSee('Dit had je als laatste open op TTV Merwestad.')
        ->assertSee('Elke site heeft zijn eigen deur.')
        ->assertDontSee('De site wordt bewerkbaar.');
});

it('links to help with logging in when the host has a help page', function (): void {
    $this->get('made-cms')->assertDontSee('Hulp bij inloggen');

    config(['made-cms-package.mail.help_url' => 'https://made-foryou.nl/hulp']);

    $this->get('made-cms')
        ->assertSee('Hulp bij inloggen')
        ->assertSee('href="https://made-foryou.nl/hulp"', false);
});

it('takes a logged-in editor straight on to where they were', function (): void {
    $this->actingAs(Editor::factory()->create(['name' => 'Menno Tempelaar']), 'made-cms')
        ->get('made-cms')
        ->assertOk()
        ->assertSee('data-start="continue"', false)
        ->assertSee('"editor":{"first_name":"Menno","initial":"M"}', false);
});

it('comes back to the page after the link in the mail and shows the editor is in', function (): void {
    Sleep::fake();
    Notification::fake();
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    $this->postJson('made-cms/login/email', ['email' => 'menno@made-foryou.nl'])->assertOk();
    $this->postJson('made-cms/login/code', ['return_to' => '/made-cms'])->assertOk();

    $link = '';

    Notification::assertSentOnDemand(LoginCodeNotification::class, function (LoginCodeNotification $notification) use (&$link): bool {
        $link = (string) parse_url($notification->url, PHP_URL_PATH);

        return true;
    });

    $this->post($link)->assertRedirect('/made-cms');

    $this->get('made-cms')->assertSee('data-start="done"', false);
    $this->get('made-cms')->assertSee('data-start="continue"', false);
});
