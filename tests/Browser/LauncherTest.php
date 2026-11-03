<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\CreateEditor;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    config(['made-cms-package.launcher.allowed_ips' => ['127.0.0.1', '::1']]);

    Page::factory()->published()->at('/')->create(['title' => 'Home']);

    $this->editor = CreateEditor::run(new NewEditor(email: 'editor@example.com', name: 'Menno Tempelaar', password: 'password', roles: [Role::Admin]));
});

it('shows the edit bar after an editor logs in with a password', function (): void {
    $page = visit('/');

    // The first visit opens the panel by itself after a moment; this test opens it by hand.
    $page->script("() => localStorage.setItem('made-cms-launcher-intro', '1')");

    $page->refresh()
        ->click('.beheer')
        ->type('#made-cms-email', 'editor@example.com')
        ->click('form[data-form="email"] button[type="submit"]')
        ->click('[data-methode="password"]')
        ->type('#made-cms-password', 'password')
        ->click('form[data-form="password"] button[type="submit"]')
        ->click('[data-form="offer"] [data-actie="done"]');

    // The panel closes, the done step lingers and the bar slides in: wait for the launcher to switch views.
    expect($page->script(waitUntil("document.querySelector('made-cms-launcher')?.dataset.view === 'edit_bar'")))->toBeTrue();

    $page->assertVisible('.balk')
        ->assertSeeIn('.balk', __('made-cms-package::launcher.bar.logout'))
        ->assertNoJavaScriptErrors();
});

it('shows the edit bar right away to an editor who is logged in', function (): void {
    /** @var Editor $editor */
    $editor = $this->editor;

    $this->actingAs($editor, 'made-cms');

    visit('/')
        ->assertVisible('.balk')
        ->assertSeeIn('.balk', __('made-cms-package::launcher.bar.logout'))
        ->assertNoJavaScriptErrors();
});
