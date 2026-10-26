<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;

it('logs the editor out and tells the launcher what to show next', function (): void {
    config(['made-cms-package.launcher.allowed_ips' => ['203.0.113.0/24']]);
    $editor = Editor::factory()->create();
    $tokenBefore = session()->token();

    $this->actingAs($editor, 'made-cms')
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->postJson('cms/logout')
        ->assertOk()
        ->assertJsonPath('view', 'tile')
        ->assertJsonPath('csrf_token', session()->token());

    expect(auth('made-cms')->check())->toBeFalse()
        ->and(session()->token())->not->toBe($tokenBefore);
});

it('hides the launcher after logging out from an unknown ip address', function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->postJson('cms/logout')
        ->assertJsonPath('view', 'hidden');
});

it('keeps the rest of the session, such as a login to the host site', function (): void {
    session(['host-cart' => ['item']]);

    $this->actingAs(Editor::factory()->create(), 'made-cms')->postJson('cms/logout')->assertOk();

    expect(session('host-cart'))->toBe(['item']);
});

it('also works without javascript and goes back to the page', function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->from('/over-ons')
        ->post('cms/logout')
        ->assertRedirect('/over-ons');

    expect(auth('made-cms')->check())->toBeFalse();
});

it('answers a guest without an error', function (): void {
    $this->postJson('cms/logout')->assertOk()->assertJsonPath('view', 'hidden');
});
