<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\UpdateSitemapPage;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;
use Made\CmsPackage\Content\Sitemap\SitemapXml;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'made-cms-package.sitemap.robots' => []]);
    app()->instance(SitePrefixes::class, new SitePrefixes(['', 'nl']));
});

it('changes whether the sitemap lists a page and builds the sitemaps at once, before the answer', function (): void {
    $editor = Editor::factory()->create();
    Page::factory()->published()->at('/')->create();
    $privacy = Page::factory()->published()->at('/privacy')->create(['title' => 'Privacy']);

    UpdateSitemapPage::run($privacy, PageMetaChanges::sitemap(false, $editor->id));

    $latest = Sitemap::query()->forPrefix('')->newestFirst()->firstOrFail();

    expect($privacy->refresh()->in_sitemap)->toBeFalse()
        ->and($privacy->updated_by)->toBe($editor->id)
        ->and(SitemapXml::locations($latest->xml))->toBe(['https://ttvmerwestad.nl/'])
        ->and($latest->cause)->toBe(SitemapCause::Meta)
        ->and($latest->page_id)->toBe($privacy->id)
        ->and(Sitemap::query()->forPrefix('nl')->exists())->toBeTrue();
});

it('builds nothing when the page is listed as it was asked to', function (): void {
    $page = Page::factory()->published()->at('/teams')->create();

    UpdateSitemapPage::run($page, PageMetaChanges::sitemap(true));

    expect(Sitemap::query()->exists())->toBeFalse();
});
