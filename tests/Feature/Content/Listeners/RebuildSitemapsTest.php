<?php

declare(strict_types=1);

use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Events\PageDeleted;
use Made\CmsPackage\Content\Events\PageMetaUpdated;
use Made\CmsPackage\Content\Events\PageMoved;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Events\PageRestored;
use Made\CmsPackage\Content\Events\PageUpdated;
use Made\CmsPackage\Content\Events\PageVisibilityChanged;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;
use Made\CmsPackage\Content\Sitemap\SitemapXml;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl']);
});

it('builds the sitemap again after every change of a page that can change it, naming the page', function (Closure $event, SitemapCause $cause): void {
    servePages();
    $this->withoutDefer();
    $page = Page::factory()->published()->at('/teams')->create();

    event($event($page));

    $sitemap = Sitemap::query()->sole();

    expect($sitemap->cause)->toBe($cause)
        ->and($sitemap->page_id)->toBe($page->id);
})->with([
    'published' => [fn (Page $page): object => new PagePublished($page), SitemapCause::Published],
    'changed' => [fn (Page $page): object => new PageUpdated($page, PagePath::fromString('/oud'), PagePath::fromString('/teams')), SitemapCause::Updated],
    'moved' => [fn (Page $page): object => new PageMoved($page, PagePath::fromString('/oud'), PagePath::fromString('/teams')), SitemapCause::Moved],
    'deleted' => [fn (Page $page): object => new PageDeleted($page, PagePath::fromString('/teams')), SitemapCause::Deleted],
    'restored' => [fn (Page $page): object => new PageRestored($page, PagePath::fromString('/teams')), SitemapCause::Restored],
    'hidden or shown' => [fn (Page $page): object => new PageVisibilityChanged($page), SitemapCause::Visibility],
    'how Google sees it' => [fn (Page $page): object => new PageMetaUpdated($page), SitemapCause::Meta],
]);

it('waits until the answer is sent, and builds once however many pages changed', function (): void {
    servePages();
    $first = Page::factory()->create();
    $second = Page::factory()->create();

    PublishPage::run($first);
    PublishPage::run($second);

    expect(Sitemap::query()->count())->toBe(0);

    app(DeferredCallbackCollection::class)->invoke();

    expect(Sitemap::query()->sole()->page_id)->toBe($second->id);
});

it('builds the sitemap again after publishing in the cms', function (): void {
    useStripTypes();
    servePages();
    $page = Page::factory()->at('/teams')->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->postJson('made-cms/pages/'.$page->id.'/publish')
        ->assertNoContent();

    $sitemap = Sitemap::query()->sole();

    expect($sitemap->cause)->toBe(SitemapCause::Published)
        ->and(SitemapXml::locations($sitemap->xml))->toBe(['https://ttvmerwestad.nl/teams']);
});

it('builds the sitemap of every route group', function (): void {
    servePages();
    Route::middleware('web')->prefix('en')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });
    $this->withoutDefer();

    event(new PagePublished(Page::factory()->published()->at('/')->create()));

    expect(Sitemap::query()->orderBy('prefix')->pluck('prefix')->all())->toBe(['', 'en']);
});

it('builds nothing while the site serves no pages', function (): void {
    $this->withoutDefer();

    event(new PagePublished(Page::factory()->published()->at('/')->create()));

    expect(Sitemap::query()->count())->toBe(0);
});
