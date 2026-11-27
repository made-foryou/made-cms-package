<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\MeasurePageMeta;
use Made\CmsPackage\Content\Enums\MetaStanding;
use Made\CmsPackage\Content\Enums\NotShownInGoogle;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

it('measures the title google shows and the description of a page', function (): void {
    $page = Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams', 'meta_description' => 'Alle teams van de club.']);

    $measure = MeasurePageMeta::run($page);

    expect($measure->googleTitle)->toBe('Onze teams · TTV Merwestad')
        ->and($measure->title->length)->toBe(26)
        ->and($measure->description->length)->toBe(23)
        ->and($measure->notShown)->toBeNull()
        ->and($measure->shown())->toBeTrue()
        ->and($measure->standing())->toBe(MetaStanding::Ok)
        ->and($measure->needsWork())->toBeFalse();
});

it('measures the name of the site as the title of the home page', function (): void {
    $page = Page::factory()->published()->at('/')->create(['title' => 'Home']);

    expect(MeasurePageMeta::run($page)->googleTitle)->toBe('TTV Merwestad');
});

it('says a page without a description misses one, also when its title is too long', function (): void {
    $page = Page::factory()->published()->at('/teams')->withMeta(str_repeat('a', 61))->create();

    $measure = MeasurePageMeta::run($page);

    expect($measure->standing())->toBe(MetaStanding::Missing)
        ->and($measure->needsWork())->toBeTrue();
});

it('says a title or a description longer than google shows is too long', function (?string $title, string $description): void {
    $page = Page::factory()->published()->at('/teams')->withMeta($title, $description)->create();

    expect(MeasurePageMeta::run($page)->standing())->toBe(MetaStanding::Long);
})->with([
    'the title' => [str_repeat('a', 61), 'Alle teams van de club.'],
    'the description' => ['Onze teams', str_repeat('a', 156)],
]);

it('counts a title and a description near the limit as in order', function (): void {
    $page = Page::factory()->published()->at('/teams')->withMeta(str_repeat('a', 60), str_repeat('a', 155))->create();

    $measure = MeasurePageMeta::run($page);

    expect($measure->standing())->toBe(MetaStanding::Ok)
        ->and($measure->title->nearLimit)->toBeTrue()
        ->and($measure->description->nearLimit)->toBeTrue();
});

it('has no standing for a page google does not show, only the reason', function (Closure $page, NotShownInGoogle $reason): void {
    $measure = MeasurePageMeta::run($page());

    expect($measure->notShown)->toBe($reason)
        ->and($measure->shown())->toBeFalse()
        ->and($measure->standing())->toBeNull()
        ->and($measure->needsWork())->toBeFalse()
        ->and($measure->description->missing)->toBeTrue();
})->with([
    'a draft' => [static fn (): Page => Page::factory()->at('/nieuw')->create(), NotShownInGoogle::Draft],
    'a hidden page' => [static fn (): Page => Page::factory()->published()->hidden()->at('/geheim')->create(), NotShownInGoogle::Hidden],
    'a noindex page' => [static fn (): Page => Page::factory()->published()->noindex()->at('/intern')->create(), NotShownInGoogle::Noindex],
]);
