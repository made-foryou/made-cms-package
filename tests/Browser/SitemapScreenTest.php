<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad', 'made-cms-package.sitemap.robots' => []]);
    usePublicFolder();
    servePages();

    Page::factory()->published()->at('/')->atPosition(0)->create(['title' => 'Home']);
    $this->contact = Page::factory()->published()->at('/contact')->atPosition(1)->create(['title' => 'Contact']);
    Page::factory()->published()->outOfSitemap()->at('/privacy')->atPosition(2)->create(['title' => 'Privacy']);

    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms');
});

it('saves a switch as soon as it changes and gives it the focus back', function (): void {
    $screen = visit('/made-cms/sitemap')->resize(1280, 1600);

    $screen->click('[aria-label="Contact in de sitemap"]')
        ->assertSeeIn('#made-cms-sitemap-'.$this->contact->id.' .sitemaprij-opgeslagen', 'Opgeslagen')
        ->assertSeeIn('#made-cms-sitemap-'.$this->contact->id.' .sitemaprij-reden', 'Niet erin: uitgevinkt');

    $screen->script(waitUntil("document.activeElement?.getAttribute('aria-label') === 'Contact in de sitemap'"));

    expect($screen->script('() => [location.hash, document.activeElement.checked]'))->toBe(['#made-cms-sitemap-'.$this->contact->id, false])
        ->and($this->contact->refresh()->in_sitemap)->toBeFalse();

    $screen->assertNoJavaScriptErrors();
});

it('says it is busy while the sitemap is made again, sends it once and says what that gave', function (): void {
    $screen = visit('/made-cms/sitemap')->resize(1280, 1600);

    expect($screen->script(<<<'JS'
        () => {
            const form = document.querySelector('form[data-busy]');
            const button = form.querySelector('button[type="submit"]');
            let sent = 0;
            form.addEventListener('submit', (event) => { if (! event.defaultPrevented) { sent++; } event.preventDefault(); });
            form.requestSubmit();
            form.requestSubmit();
            const said = [button.textContent, button.getAttribute('aria-busy'), button.getAttribute('aria-disabled'), sent];
            delete form.dataset.sending;

            return said;
        }
        JS))->toBe(['Bezig…', 'true', 'true', 1]);

    $screen = visit('/made-cms/sitemap')->resize(1280, 1600);

    $screen->click('form[data-busy] button[type="submit"]')
        ->assertSeeIn('.sitemapbestand-status', 'Zojuist opnieuw gemaakt; het klopte al')
        ->assertSeeIn('form[data-busy] button[type="submit"]', 'Nog eens genereren')
        ->assertNoJavaScriptErrors();

    expect(Sitemap::query()->count())->toBe(1);
});

it('copies the instruction for the developer, and selects it where the browser refuses', function (): void {
    file_put_contents(public_path('sitemap.xml'), publicSitemap(['http://localhost/']));
    file_put_contents(public_path('robots.txt'), "User-agent: *\nDisallow:\n");

    $screen = visit('/made-cms/sitemap')->resize(1280, 1600);
    $screen->script(stubClipboard(refuses: false));

    $screen->click('.sitemapmelding:nth-of-type(1) [data-copy]');
    $screen->script(waitUntil("document.querySelector('.sitemapmelding:nth-of-type(1) [data-copy-status]').textContent === 'Instructie gekopieerd'"));

    expect($screen->script('() => window.madeCmsCopied'))
        ->toBe('Verwijder public/sitemap.xml uit de site (of hernoem het), zodat het CMS het adres /sitemap.xml bedient. Zodra het bestand weg is, verdwijnt deze melding.');

    $screen->script(stubClipboard(refuses: true));
    $screen->click('.sitemapmelding:nth-of-type(2) [data-copy]');
    $screen->script(waitUntil("document.querySelector('.sitemapmelding:nth-of-type(2) [data-copy-status]').textContent.startsWith('Kopiëren lukte niet')"));

    // The browser serves the site at an address of its own, which the line names.
    expect($screen->script('() => window.getSelection().toString().trim()'))
        ->toStartWith('Haal public/robots.txt weg zodat het CMS hem maakt, of voeg deze regel toe: Sitemap: http')
        ->toEndWith('/sitemap.xml');

    $screen->assertNoJavaScriptErrors();
});

it('draws the dots and the file in the dark colours of the system', function (): void {
    $screen = visit('/made-cms/sitemap')->inDarkMode();

    expect($screen->script(<<<'JS'
        () => [
            getComputedStyle(document.querySelector('.sitemaprij .bol.goed')).backgroundColor,
            getComputedStyle(document.querySelector('.codeblok')).backgroundColor,
            getComputedStyle(document.querySelector('.sitemaprij')).borderBottomColor,
        ]
        JS))->toBe(['rgb(69, 168, 118)', 'rgb(243, 241, 232)', 'rgba(243, 241, 232, 0.12)']);
});

it('fits a phone without scrolling sideways', function (): void {
    $screen = visit('/made-cms/sitemap')->resize(375, 812);

    expect($screen->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $screen->assertNoJavaScriptErrors();
});
