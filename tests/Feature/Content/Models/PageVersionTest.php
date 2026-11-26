<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Builders\PageVersionBuilder;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

it('uses the custom builder for version queries', function (): void {
    expect(PageVersion::query())->toBeInstanceOf(PageVersionBuilder::class);
});

it('reads a stored version in its own types', function (): void {
    $page = Page::factory()->create();
    $editor = Editor::factory()->create();
    $strips = [storedStrip('hero', ['kop' => 'Welkom'], id: 'hero')];
    $changes = ['first' => false, 'added' => [], 'changed' => ['hero'], 'moved' => [], 'removed' => []];

    $version = PageVersion::factory()->for($page)->create([
        'number' => 3,
        'strips' => $strips,
        'changes' => $changes,
        'published_by' => $editor->id,
        'published_at' => CarbonImmutable::parse('2026-10-20 14:12:00'),
    ]);

    $stored = PageVersion::query()->findOrFail($version->id);

    expect($stored->page_id)->toBe($page->id)
        ->and($stored->number)->toBe(3)
        ->and($stored->strips)->toBeStoredAs($strips)
        ->and($stored->changes)->toBeStoredAs($changes)
        ->and($stored->published_by)->toBe($editor->id)
        ->and($stored->published_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($stored->published_at->toDateTimeString())->toBe('2026-10-20 14:12:00')
        ->and($stored->page?->is($page))->toBeTrue()
        ->and($page->versions()->pluck('id')->all())->toBe([$version->id]);
});

it('numbers the versions of a page once', function (): void {
    $page = Page::factory()->create();
    PageVersion::factory()->for($page)->number(1)->create();

    expect(fn () => PageVersion::factory()->for($page)->number(1)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('gives every page numbers of its own', function (): void {
    PageVersion::factory()->for(Page::factory())->number(1)->create();
    PageVersion::factory()->for(Page::factory())->number(1)->create();

    expect(PageVersion::query()->count())->toBe(2);
});

it('takes the versions along when the page is removed for good', function (): void {
    $page = Page::factory()->create();
    PageVersion::factory()->for($page)->count(2)->sequence(['number' => 1], ['number' => 2])->create();

    $page->forceDelete();

    expect(PageVersion::query()->count())->toBe(0);
});

it('keeps a version when the editor who published it is gone', function (): void {
    $editor = Editor::factory()->create();
    $version = PageVersion::factory()->publishedBy($editor)->create();

    $editor->delete();

    expect($version->refresh()->published_by)->toBeNull();
});
