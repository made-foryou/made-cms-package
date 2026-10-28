<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\DescribeStrips;
use Made\CmsPackage\Content\Data\StripDescription;
use Made\CmsPackage\Content\Data\StripLookup;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Models\Strip;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('describes the strips it is asked about with the label of their type', function (): void {
    $hero = Strip::factory()->create(['type' => 'hero']);
    $figures = Strip::factory()->create(['type' => 'cijfers']);
    Strip::factory()->create(['type' => 'hero']);

    expect(DescribeStrips::run(new StripLookup(ids: [$figures->id, $hero->id])))->toEqual([
        new StripDescription(id: $hero->id, type: 'hero', label: 'Hero'),
        new StripDescription(id: $figures->id, type: 'cijfers', label: 'Cijfers'),
    ]);
});

it('leaves out ids without a strip', function (): void {
    $hero = Strip::factory()->create(['type' => 'hero']);

    expect(DescribeStrips::run(new StripLookup(ids: [$hero->id, 999])))->toHaveCount(1);
});

it('labels a strip whose type has no definition with its key', function (): void {
    $strip = Strip::factory()->create(['type' => 'teams']);

    expect(DescribeStrips::run(new StripLookup(ids: [$strip->id]))[0]->label)->toBe('teams');
});

it('reports a broken definition and still describes every strip, labelled with its key', function (): void {
    Exceptions::fake();
    File::put($this->content.'/strips/teams.json', '{"label": ');
    $hero = Strip::factory()->create(['type' => 'hero']);

    expect(DescribeStrips::run(new StripLookup(ids: [$hero->id]))[0]->label)->toBe('hero');

    Exceptions::assertReported(InvalidStripType::class);
});
