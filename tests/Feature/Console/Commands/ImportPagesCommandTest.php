<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('creates the pages that do not exist yet', function (): void {
    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('aangemaakt met 2 stroken')
        ->assertSuccessful();

    expect(Page::query()->forPath('/')->sole()->strips)->toHaveCount(2);
});

it('skips a page that already exists, so it can run on every deploy', function (): void {
    $this->artisan('made-cms:pages')->assertSuccessful();

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('bestaat al, overgeslagen')
        ->assertSuccessful();

    expect(Page::query()->count())->toBe(1);
});

it('says so when there are no page files', function (): void {
    File::deleteDirectory($this->content.'/pages');

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('Geen paginabestanden gevonden')
        ->assertSuccessful();
});

it('fails on a page file it cannot read, before creating anything', function (): void {
    File::put($this->content.'/pages/lid-worden.json', '{"path": "lid-worden"}');

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('Page file [lid-worden.json]')
        ->assertFailed();

    expect(Page::query()->count())->toBe(0);
});

it('still creates the other pages when the strips of one do not fit, and fails', function (): void {
    File::put($this->content.'/pages/lid-worden.json', json_encode([
        'path' => '/lid-worden',
        'title' => 'Lid worden',
        'strips' => [['type' => 'teams', 'content' => []]],
    ], JSON_THROW_ON_ERROR));

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('/lid-worden is niet aangemaakt')
        ->assertFailed();

    expect(Page::query()->pluck('path')->all())->toBe(['/']);
});

it('reports in English for an English site', function (): void {
    app()->setLocale('en');

    $this->artisan('made-cms:pages')
        ->expectsOutputToContain('created with 2 strips')
        ->assertSuccessful();
});
