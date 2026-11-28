<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'made-cms-package.sitemap.robots' => ['*' => ['/zoeken']]]);
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));
    usePublicFolder();
    servePages();
    $this->editor = Editor::factory()->withRoles(Role::SeoSpecialist)->create(['name' => 'Muriël']);
    $this->actingAs($this->editor, 'made-cms');
});

it('makes the sitemap again by hand and comes back at the file, saying once what that gave', function (): void {
    Page::factory()->published()->at('/')->create();
    Page::factory()->published()->at('/zoeken')->create(['title' => 'Zoeken']);

    $this->post('made-cms/sitemap/build')->assertRedirect('/made-cms/sitemap#made-cms-sitemap-bestand');

    $sitemap = Sitemap::query()->sole();
    expect($sitemap->cause)->toBe(SitemapCause::Manual)
        ->and($sitemap->editor_id)->toBe($this->editor->id);

    $screen = (string) $this->get('made-cms/sitemap')->getContent();

    expect($screen)
        ->toContain('<span class="sitemapbestand-status" role="status"><span class="bol goed" aria-hidden="true"></span>Zojuist opnieuw gemaakt · 1 adres · 1 fout</span>')
        ->toContain('<button type="submit" class="knop">Nog eens genereren</button>')
        ->toContain('Het bestand staat live op sitemap.xml. Zoekmachines halen het vanzelf opnieuw op.')
        ->toContain('Gemaakt vandaag, 10:00, met de hand door Muriël');

    expect((string) $this->get('made-cms/sitemap')->getContent())
        ->toContain('Laatst gemaakt vandaag, 10:00, met de hand door Muriël · 1 adres · 1 fout')
        ->toContain('<button type="submit" class="knop">Opnieuw genereren</button>');
});

it('says the file was right already when making it again changed nothing', function (): void {
    Page::factory()->published()->at('/')->create();
    $this->post('made-cms/sitemap/build');

    $this->post('made-cms/sitemap/build');

    expect((string) $this->get('made-cms/sitemap')->getContent())->toContain('Zojuist opnieuw gemaakt; het klopte al · 1 adres · geen fouten</span>')
        ->and(Sitemap::query()->count())->toBe(1);
});

it('opens only for whoever opens the screen of the sitemap', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::ContentManager)->create(), 'made-cms')
        ->post('made-cms/sitemap/build')
        ->assertForbidden();

    expect(Sitemap::query()->exists())->toBeFalse();
});
