<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Events\PageMetaUpdated;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl']);
    usePublicFolder();
    servePages();
    $this->editor = Editor::factory()->withRoles(Role::SeoSpecialist)->create();
    $this->actingAs($this->editor, 'made-cms');
});

it('takes a page out of the sitemap and keeps its texts and whether Google may show it', function (): void {
    $page = Page::factory()->published()->at('/privacy')->withMeta('Privacy · TTV', 'Hoe we omgaan met je gegevens.')->create(['title' => 'Privacy']);

    $this->put('made-cms/sitemap/'.$page->id, ['in_sitemap' => '0'])
        ->assertRedirect('/made-cms/sitemap#made-cms-sitemap-'.$page->id);

    $page->refresh();

    expect($page->in_sitemap)->toBeFalse()
        ->and($page->meta_title)->toBe('Privacy · TTV')
        ->and($page->meta_description)->toBe('Hoe we omgaan met je gegevens.')
        ->and($page->indexable)->toBeTrue()
        ->and($page->updated_by)->toBe($this->editor->id);
});

it('puts a page in the sitemap again, and the sitemap follows', function (): void {
    Event::fake([PageMetaUpdated::class]);
    $page = Page::factory()->published()->outOfSitemap()->at('/privacy')->create();

    $this->put('made-cms/sitemap/'.$page->id, ['in_sitemap' => '1'])->assertRedirect();

    expect($page->refresh()->in_sitemap)->toBeTrue();
    Event::assertDispatched(PageMetaUpdated::class);
});

it('comes back at the page in its route group, with the focus on its switch and once that it saved', function (): void {
    Route::middleware('web')->prefix('nl')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });
    $page = Page::factory()->published()->at('/privacy')->create(['title' => 'Privacy']);

    $this->put('made-cms/sitemap/'.$page->id, ['in_sitemap' => '0', 'groep' => 'nl'])
        ->assertRedirect('/made-cms/sitemap?groep=nl#made-cms-sitemap-'.$page->id);

    $screen = (string) $this->get('made-cms/sitemap?groep=nl')->getContent();

    preg_match('/<li class="sitemaprij" id="made-cms-sitemap-'.$page->id.'">.*?<\/li>/s', $screen, $row);
    expect($row[0])
        ->toContain(' data-refocus')
        ->toContain('<span class="sitemaprij-opgeslagen" role="status"><span class="bol goed" aria-hidden="true"></span>Opgeslagen</span>')
        ->toContain('Niet erin: uitgevinkt');

    expect((string) $this->get('made-cms/sitemap?groep=nl')->getContent())->not->toContain('Opgeslagen</span>');
});

it('refuses a switch that is neither on nor off, back at the page', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create();

    $this->put('made-cms/sitemap/'.$page->id, ['in_sitemap' => 'misschien'])
        ->assertRedirect('/made-cms/sitemap#made-cms-sitemap-'.$page->id)
        ->assertSessionHasErrors('in_sitemap');

    expect($page->refresh()->in_sitemap)->toBeTrue();
});

it('opens only for whoever opens the screen of the sitemap', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create();

    $this->actingAs(Editor::factory()->withRoles(Role::ContentManager)->create(), 'made-cms')
        ->put('made-cms/sitemap/'.$page->id, ['in_sitemap' => '0'])
        ->assertForbidden();

    expect($page->refresh()->in_sitemap)->toBeTrue();
});

it('sends a guest to the login', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create();
    auth('made-cms')->logout();

    $this->put('made-cms/sitemap/'.$page->id, ['in_sitemap' => '0'])->assertRedirect(route('made-cms-package.login'));
});
