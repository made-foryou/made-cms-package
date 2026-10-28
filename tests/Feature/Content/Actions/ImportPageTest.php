<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\ImportPage;
use Made\CmsPackage\Content\Data\PageImport;
use Made\CmsPackage\Content\Data\StripImport;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('creates a published page with its strips in order', function (): void {
    $this->freezeSecond();

    $created = ImportPage::run(new PageImport(path: '/', title: 'Home', strips: [
        new StripImport(type: 'hero', content: ['title' => 'TTV Merwestad']),
        new StripImport(type: 'cijfers', content: ['items' => [['value' => '60+', 'label' => 'Jaar actief']]]),
    ]));

    $page = Page::query()->forPath('/')->sole();

    expect($created)->toBeTrue()
        ->and($page->title)->toBe('Home')
        ->and($page->status)->toBe(PageStatus::Published)
        ->and($page->published_at?->equalTo(now()))->toBeTrue()
        ->and($page->strips->toArray())->toBe([
            ['id' => $page->strips[0]->id, 'type' => 'hero', 'content' => [
                'eyebrow' => '',
                'title' => 'TTV Merwestad',
                'intro' => '',
                'photo' => null,
                'buttons' => [],
            ]],
            ['id' => $page->strips[1]->id, 'type' => 'cijfers', 'content' => [
                'items' => [['value' => '60+', 'label' => 'Jaar actief']],
            ]],
        ]);
});

it('leaves a page that already exists alone', function (): void {
    $page = Page::factory()->create(['path' => '/', 'title' => 'Welkom']);
    Strip::factory()->for($page)->create(['type' => 'hero', 'content' => ['title' => 'Bewerkt in het CMS']]);

    $created = ImportPage::run(new PageImport(path: '/', title: 'Home', strips: [
        new StripImport(type: 'hero', content: ['title' => 'TTV Merwestad']),
    ]));

    expect($created)->toBeFalse()
        ->and(Page::query()->count())->toBe(1)
        ->and($page->refresh()->title)->toBe('Welkom')
        ->and($page->strips->sole()->content)->toBe(['title' => 'Bewerkt in het CMS']);
});

it('refuses a strip type the site does not have, without creating anything', function (): void {
    expect(fn () => ImportPage::run(new PageImport(path: '/', title: 'Home', strips: [
        new StripImport(type: 'hero'),
        new StripImport(type: 'teams'),
    ])))->toThrow(InvalidStripContent::class, 'Strip type [teams] has no definition in the strips folder');

    expect(Page::query()->count())->toBe(0)
        ->and(Strip::query()->count())->toBe(0);
});

it('refuses content that does not fit its strip type, without creating anything', function (): void {
    expect(fn () => ImportPage::run(new PageImport(path: '/', title: 'Home', strips: [
        new StripImport(type: 'hero', content: ['title' => 'TTV Merwestad']),
        new StripImport(type: 'cijfers', content: ['items' => 'veel']),
    ])))->toThrow(InvalidStripContent::class, 'Content of strip type [cijfers] is invalid at [items]: expected a list of items');

    expect(Page::query()->count())->toBe(0);
});
