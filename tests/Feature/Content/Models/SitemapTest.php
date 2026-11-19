<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Builders\SitemapBuilder;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

it('uses the custom builder for sitemap queries', function (): void {
    expect(Sitemap::query())->toBeInstanceOf(SitemapBuilder::class);
});

it('reads a stored sitemap in its own types', function (): void {
    $page = Page::factory()->create();
    $editor = Editor::factory()->create();

    $sitemap = Sitemap::factory()->create([
        'prefix' => 'nl',
        'xml' => '<urlset/>',
        'address_count' => 12,
        'errors' => [['path' => '/zoeken', 'label' => 'Zoeken', 'reason' => 'blocked']],
        'cause' => SitemapCause::Published,
        'page_id' => $page->id,
        'editor_id' => $editor->id,
        'built_at' => CarbonImmutable::parse('2026-11-06 11:20:00'),
    ]);

    $stored = Sitemap::query()->findOrFail($sitemap->id);

    expect($stored->prefix)->toBe('nl')
        ->and($stored->xml)->toBe('<urlset/>')
        ->and($stored->address_count)->toBe(12)
        ->and($stored->errors)->toBe([['path' => '/zoeken', 'label' => 'Zoeken', 'reason' => 'blocked']])
        ->and($stored->cause)->toBe(SitemapCause::Published)
        ->and($stored->page_id)->toBe($page->id)
        ->and($stored->editor_id)->toBe($editor->id)
        ->and($stored->built_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($stored->built_at->toDateTimeString())->toBe('2026-11-06 11:20:00');
});

it('keeps a sitemap when the page or the editor that led to it is removed for good', function (): void {
    $page = Page::factory()->create();
    $editor = Editor::factory()->create();
    $sitemap = Sitemap::factory()->create(['page_id' => $page->id, 'editor_id' => $editor->id]);

    $page->forceDelete();
    $editor->delete();

    expect($sitemap->refresh()->page_id)->toBeNull()
        ->and($sitemap->editor_id)->toBeNull();
});
