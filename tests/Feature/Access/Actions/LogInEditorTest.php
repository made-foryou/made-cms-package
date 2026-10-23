<?php

declare(strict_types=1);

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Actions\LogInEditor;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Events\EditorLoggedIn;
use Made\CmsPackage\Access\Models\Editor;

it('logs the editor in to the cms guard only', function (): void {
    $editor = Editor::factory()->create();

    LogInEditor::run($editor, LoginMethod::Code, false);

    expect(auth('made-cms')->user()?->is($editor))->toBeTrue()
        ->and(auth('web')->check())->toBeFalse();
});

it('remembers the editor for the configured number of days', function (): void {
    $this->freezeSecond();
    config(['made-cms-package.auth.remember_days' => 30]);
    $editor = Editor::factory()->create(['remember_token' => null]);

    LogInEditor::run($editor, LoginMethod::Password, true);

    /** @var SessionGuard $guard */
    $guard = auth('made-cms');
    $cookie = $guard->getCookieJar()->queued($guard->getRecallerName());

    expect($cookie?->getExpiresTime())->toBe(now()->addDays(30)->getTimestamp())
        ->and($editor->fresh()?->remember_token)->not->toBeNull();
});

it('dispatches an event with the method that was used', function (): void {
    Event::fake([EditorLoggedIn::class]);
    $editor = Editor::factory()->create();

    LogInEditor::run($editor, LoginMethod::Code, false);

    Event::assertDispatched(EditorLoggedIn::class, fn (EditorLoggedIn $event): bool => $event->editor->is($editor) && $event->method === LoginMethod::Code);
});
