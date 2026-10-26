<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\OpenLoginPage;
use Made\CmsPackage\Access\Data\LoginPageVisit;
use Made\CmsPackage\Access\Enums\LoginPageStep;
use Made\CmsPackage\Access\Models\Editor;

it('starts a visitor who is not logged in at the e-mail step', function (bool $arrived): void {
    expect(OpenLoginPage::run(new LoginPageVisit(editor: null, arrived: $arrived)))->toBe(LoginPageStep::Email);
})->with([
    'fresh visit' => [false],
    'stale arrival flag' => [true],
]);

it('welcomes an editor who just arrived through the mail link', function (): void {
    $visit = new LoginPageVisit(editor: Editor::factory()->make(), arrived: true);

    expect(OpenLoginPage::run($visit))->toBe(LoginPageStep::Done);
});

it('takes a logged-in editor straight on to where they were', function (): void {
    $visit = new LoginPageVisit(editor: Editor::factory()->make(), arrived: false);

    expect(OpenLoginPage::run($visit))->toBe(LoginPageStep::Continue);
});

it('opens for everyone, also with the launcher switched off or without allowed ip addresses', function (): void {
    config(['made-cms-package.launcher.enabled' => false, 'made-cms-package.launcher.allowed_ips' => []]);

    expect(OpenLoginPage::run(new LoginPageVisit(editor: null, arrived: false)))->toBe(LoginPageStep::Email);
});
