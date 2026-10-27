<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;

it('creates an editor with a password', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl', '--name' => 'Menno Tempelaar', '--role' => ['admin']])
        ->expectsQuestion('Wachtwoord (leeg laten om alleen met een inlogcode in te loggen)', 'geheim-wachtwoord')
        ->expectsOutputToContain('menno@made-foryou.nl')
        ->assertSuccessful();

    $editor = Editor::query()->forEmail('menno@made-foryou.nl')->sole();

    expect($editor->name)->toBe('Menno Tempelaar')
        ->and(Hash::check('geheim-wachtwoord', (string) $editor->password))->toBeTrue()
        ->and($editor->roles->all())->toBe([Role::Admin]);
});

it('gives the editor every role passed as an option', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl', '--name' => 'Menno', '--role' => ['owner', 'seo-specialist']])
        ->expectsQuestion('Wachtwoord (leeg laten om alleen met een inlogcode in te loggen)', '')
        ->assertSuccessful();

    expect(Editor::query()->forEmail('menno@made-foryou.nl')->sole()->roles->all())->toBe([Role::SeoSpecialist, Role::Owner]);
});

it('asks for the name and the roles when they are not given', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl'])
        ->expectsQuestion('Naam', 'Menno')
        ->expectsChoice('Rollen (kies er één of meer, gescheiden door komma’s)', ['content-manager', 'owner'], [
            'admin' => 'Admin',
            'webmaster' => 'Beheerder',
            'content-manager' => 'Contentbeheerder',
            'seo-specialist' => 'SEO-specialist',
            'owner' => 'Eigenaar',
        ])
        ->expectsQuestion('Wachtwoord (leeg laten om alleen met een inlogcode in te loggen)', '')
        ->assertSuccessful();

    $editor = Editor::query()->forEmail('menno@made-foryou.nl')->sole();

    expect($editor->password)->toBeNull()
        ->and($editor->roles->all())->toBe([Role::ContentManager, Role::Owner]);
});

it('refuses an unknown role', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl', '--name' => 'Menno', '--role' => ['admin', 'redacteur']])
        ->expectsOutputToContain('redacteur is geen bestaande rol')
        ->assertFailed();

    expect(Editor::query()->count())->toBe(0);
});

it('refuses an editor without a role', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl', '--name' => 'Menno'])
        ->expectsQuestion('Rollen (kies er één of meer, gescheiden door komma’s)', [])
        ->expectsOutputToContain('minstens één rol')
        ->assertFailed();

    expect(Editor::query()->count())->toBe(0);
});

it('refuses an invalid e-mail address', function (): void {
    $this->artisan('made-cms:editor', ['email' => 'geen-adres', '--name' => 'Menno', '--role' => ['admin']])
        ->expectsOutputToContain('geen geldig e-mailadres')
        ->assertFailed();

    expect(Editor::query()->count())->toBe(0);
});

it('refuses an e-mail address that already has an account', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    $this->artisan('made-cms:editor', ['email' => 'menno@made-foryou.nl', '--name' => 'Menno', '--role' => ['admin']])
        ->expectsQuestion('Wachtwoord (leeg laten om alleen met een inlogcode in te loggen)', '')
        ->expectsOutputToContain('bestaat al')
        ->assertFailed();
});
