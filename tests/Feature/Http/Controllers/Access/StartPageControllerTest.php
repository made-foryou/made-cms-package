<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
});

function startPageFor(Role ...$roles): string
{
    $editor = Editor::factory()->withRoles(...$roles)->create(['name' => 'Menno Tempelaar']);

    return (string) test()->actingAs($editor, 'made-cms')->get('made-cms/start')->assertOk()->getContent();
}

/**
 * The menu groups of the page, each with the labels of its items, in the order of the page.
 *
 * @return array<string, list<string>>
 */
function startPageMenu(string $html): array
{
    preg_match('/<nav class="zijbalk"[^>]*>(.*?)<\/nav>/s', $html, $nav);
    preg_match_all('/<div class="navblok">\s*<p class="navgroep">(.*?)<\/p>(.*?)<\/div>/s', $nav[1] ?? '', $groups, PREG_SET_ORDER);

    $menu = [];

    foreach ($groups as [, $label, $items]) {
        preg_match_all('/<a class="navitem[^"]*"[^>]*>.*?<\/span>(.*?)<\/a>/s', $items, $links);
        $menu[html_entity_decode($label)] = array_map(fn (string $link): string => html_entity_decode(trim($link)), $links[1]);
    }

    return $menu;
}

it('serves the start page on the cms prefix', function (): void {
    expect(route('made-cms-package.start', [], false))->toBe('/made-cms/start');
});

it('sends a guest to the login page', function (): void {
    $this->get('made-cms/start')
        ->assertRedirect(route('made-cms-package.login'));
});

it('is never stored by a cache, framed or indexed', function (): void {
    $response = $this->actingAs(Editor::factory()->withRoles(Role::Owner)->create(), 'made-cms')
        ->get('made-cms/start')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<title>Start · TTV Merwestad</title>', false);

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('gets no launcher tile on top of it', function (): void {
    config(['made-cms-package.launcher.allowed_ips' => ['127.0.0.1']]);

    expect(startPageFor(Role::Owner))->not->toContain('<made-cms-launcher');
});

it('shows the menu of the editor’s roles', function (array $roles, array $menu): void {
    expect(startPageMenu(startPageFor(...$roles)))->toBe($menu);
})->with([
    'content manager' => [[Role::ContentManager], [
        'Je site' => ['Alle pagina’s'],
        'Verzamelingen' => ['Nieuws', 'Media', 'Berichten'],
    ]],
    'seo specialist' => [[Role::SeoSpecialist], [
        'Je site' => ['Alle pagina’s'],
        'Vindbaarheid' => ['Meta-overzicht', 'Alt-teksten', 'Links', 'Redirects', 'Sitemap', 'Zoekwoorden'],
    ]],
    'webmaster' => [[Role::Webmaster], [
        'Je site' => ['Alle pagina’s'],
        'Verzamelingen' => ['Nieuws', 'Media', 'Berichten'],
        'Beheer' => ['Gebruikers en rollen', 'Formulieren', 'Back-ups', 'Logboek', 'Domein en certificaat'],
    ]],
    'owner' => [[Role::Owner], [
        'Je site' => ['Alle pagina’s'],
        'Verzamelingen' => ['Nieuws', 'Berichten'],
    ]],
    'admin' => [[Role::Admin], [
        'Je site' => ['Alle pagina’s'],
        'Verzamelingen' => ['Nieuws', 'Media', 'Berichten'],
        'Vindbaarheid' => ['Meta-overzicht', 'Alt-teksten', 'Links', 'Redirects', 'Sitemap', 'Zoekwoorden'],
        'Beheer' => ['Gebruikers en rollen', 'Formulieren', 'Back-ups', 'Logboek', 'Domein en certificaat'],
    ]],
    'content manager and seo specialist' => [[Role::ContentManager, Role::SeoSpecialist], [
        'Je site' => ['Alle pagina’s'],
        'Verzamelingen' => ['Nieuws', 'Media', 'Berichten'],
        'Vindbaarheid' => ['Meta-overzicht', 'Alt-teksten', 'Links', 'Redirects', 'Sitemap', 'Zoekwoorden'],
    ]],
]);

it('lists the pages of the menu under the site', function (): void {
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons']);
    Page::factory()->atPosition(0)->at('/')->create(['title' => 'Home']);
    Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);
    Page::factory()->atPosition(2)->inMenu(false)->at('/bedankt')->create(['title' => 'Bedankt']);

    expect(startPageMenu(startPageFor(Role::Owner))['Je site'])->toBe(['Alle pagina’s', 'Home', 'Over ons', 'Team']);
});

it('links only to the screens the editor opens, from the menu and from the blocks', function (Role $role): void {
    $html = startPageFor($role);

    foreach (Screen::cases() as $screen) {
        $link = 'href="/made-cms/'.$screen->value.'"';

        in_array($screen, $role->screens(), true)
            ? expect($html)->toContain($link)
            : expect($html)->not->toContain($link);
    }
})->with(Role::cases());

it('marks start as the current menu item', function (): void {
    $html = startPageFor(Role::Owner);

    expect($html)->toContain('href="/made-cms/start" aria-current="page"')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1);
});

it('greets the editor by first name and shows their roles', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-10-28 09:30'));

    expect(startPageFor(Role::ContentManager, Role::SeoSpecialist))
        ->toContain('Goedemorgen, Menno.')
        ->toContain('Woensdag 28 oktober')
        ->toContain('Contentbeheerder · SEO-specialist');
});

it('greets by the time of day', function (string $time, string $greeting): void {
    Carbon::setTestNow(Carbon::parse("2026-10-28 {$time}"));

    expect(startPageFor(Role::Owner))->toContain($greeting.', Menno.');
})->with([
    ['06:00', 'Goedemorgen'],
    ['12:00', 'Goedemiddag'],
    ['18:00', 'Goedenavond'],
    ['02:00', 'Goedenavond'],
]);

it('fills the blocks with the example of the editor’s role', function (Role $role, string $seen, string $unseen): void {
    expect(startPageFor($role))
        ->toContain('Verder waar je was')
        ->toContain('Hier moet iets mee')
        ->toContain('Hoe je site het doet')
        ->toContain('Snel naar')
        ->toContain($seen)
        ->not->toContain($unseen);
})->with([
    [Role::ContentManager, 'Over ons staat al 6 dagen als concept', '7 pagina’s zonder meta-omschrijving'],
    [Role::SeoSpecialist, '7 pagina’s zonder meta-omschrijving', 'Over ons staat al 6 dagen als concept'],
    [Role::Webmaster, 'Contactformulier: 1 verzending mislukt', '7 pagina’s zonder meta-omschrijving'],
    [Role::Owner, '2 berichten wachten op antwoord', '7 pagina’s zonder meta-omschrijving'],
    [Role::Admin, '7 pagina’s zonder meta-omschrijving', 'Contactformulier: 1 verzending mislukt'],
]);

it('leaves out the example items of screens the editor does not open', function (): void {
    expect(startPageFor(Role::ContentManager, Role::Owner))
        ->toContain('Over ons staat al 6 dagen als concept')
        ->not->toContain('7 pagina’s zonder meta-omschrijving');
});

it('only links to the help of MADE when there is a help url', function (): void {
    expect(startPageFor(Role::Owner))->not->toContain('Hulp van MADE');

    config(['made-cms-package.mail.help_url' => 'https://made-foryou.nl/hulp']);

    expect(startPageFor(Role::Owner))
        ->toContain('Hulp van MADE')
        ->toContain('href="https://made-foryou.nl/hulp"');
});

it('speaks English when the launcher does', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);
    Carbon::setTestNow(Carbon::parse('2026-10-28 15:00'));

    expect(startPageFor(Role::SeoSpecialist))
        ->toContain('Good afternoon, Menno.')
        ->toContain('Wednesday 28 October')
        ->toContain('Your site')
        ->toContain('Pick up where you left off');
});
