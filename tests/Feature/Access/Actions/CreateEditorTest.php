<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Made\CmsPackage\Access\Actions\CreateEditor;
use Made\CmsPackage\Access\Data\NewEditor;
use Made\CmsPackage\Access\Events\EditorCreated;
use Made\CmsPackage\Access\Exceptions\EditorAlreadyExists;
use Made\CmsPackage\Access\Models\Editor;

it('creates an editor with a hashed password', function (): void {
    $editor = CreateEditor::run(new NewEditor(email: 'menno@made-foryou.nl', name: 'Menno Tempelaar', password: 'geheim-wachtwoord'));

    $stored = Editor::query()->findOrFail($editor->id);

    expect($stored->email)->toBe('menno@made-foryou.nl')
        ->and($stored->name)->toBe('Menno Tempelaar')
        ->and($stored->password)->not->toBe('geheim-wachtwoord')
        ->and(Hash::check('geheim-wachtwoord', (string) $stored->password))->toBeTrue();
});

it('creates an editor without a password, who logs in with a code', function (): void {
    $editor = CreateEditor::run(new NewEditor(email: 'menno@made-foryou.nl', name: 'Menno', password: null));

    expect(Editor::query()->findOrFail($editor->id)->password)->toBeNull();
});

it('stores the e-mail address trimmed and in lower case', function (): void {
    $editor = CreateEditor::run(new NewEditor(email: '  Menno@Made-ForYou.nl ', name: 'Menno', password: null));

    expect($editor->email)->toBe('menno@made-foryou.nl');
});

it('refuses a second editor with the same e-mail address', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    expect(fn () => CreateEditor::run(new NewEditor(email: 'MENNO@made-foryou.nl', name: 'Menno', password: null)))
        ->toThrow(EditorAlreadyExists::class);

    expect(Editor::query()->count())->toBe(1);
});

it('dispatches an event for the new editor', function (): void {
    Event::fake([EditorCreated::class]);

    $editor = CreateEditor::run(new NewEditor(email: 'menno@made-foryou.nl', name: 'Menno', password: null));

    Event::assertDispatched(EditorCreated::class, fn (EditorCreated $event): bool => $event->editor->is($editor));
});
