<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Made\CmsPackage\Access\Models\Editor;

it('creates an editor with a password', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl', '--name' => 'Menno Tempelaar'])
        ->expectsQuestion('Wachtwoord (leeg laten om alleen met een inlogcode in te loggen)', 'geheim-wachtwoord')
        ->expectsOutputToContain('menno@made-foryou.nl')
        ->assertSuccessful();

    $editor = Editor::query()->forEmail('menno@made-foryou.nl')->sole();

    expect($editor->name)->toBe('Menno Tempelaar')
        ->and(Hash::check('geheim-wachtwoord', (string) $editor->password))->toBeTrue();
});

it('asks for the name when it is not given', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl'])
        ->expectsQuestion('Naam', 'Menno')
        ->expectsQuestion('Wachtwoord (leeg laten om alleen met een inlogcode in te loggen)', '')
        ->assertSuccessful();

    expect(Editor::query()->forEmail('menno@made-foryou.nl')->sole()->password)->toBeNull();
});

it('refuses an invalid e-mail address', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'geen-adres', '--name' => 'Menno'])
        ->expectsOutputToContain('geen geldig e-mailadres')
        ->assertFailed();

    expect(Editor::query()->count())->toBe(0);
});

it('refuses an e-mail address that already has an account', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl', '--name' => 'Menno'])
        ->expectsQuestion('Wachtwoord (leeg laten om alleen met een inlogcode in te loggen)', '')
        ->expectsOutputToContain('bestaat al')
        ->assertFailed();
});
