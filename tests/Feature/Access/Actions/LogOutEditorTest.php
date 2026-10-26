<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Actions\LogOutEditor;
use Made\CmsPackage\Access\Events\EditorLoggedOut;
use Made\CmsPackage\Access\Models\Editor;

it('logs the editor out of the cms guard and leaves the host guard alone', function (): void {
    $editor = Editor::factory()->create();
    $siteUser = new GenericUser(['id' => 7]);

    auth('web')->setUser($siteUser);
    auth('made-cms')->login($editor);

    LogOutEditor::run($editor);

    expect(auth('made-cms')->check())->toBeFalse()
        ->and(auth('web')->user())->toBe($siteUser);
});

it('dispatches an event', function (): void {
    Event::fake([EditorLoggedOut::class]);
    $editor = Editor::factory()->create();
    auth('made-cms')->login($editor);

    LogOutEditor::run($editor);

    Event::assertDispatched(EditorLoggedOut::class, fn (EditorLoggedOut $event): bool => $event->editor->is($editor));
});
