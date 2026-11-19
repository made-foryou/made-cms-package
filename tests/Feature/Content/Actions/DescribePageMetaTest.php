<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Actions\DescribePageMeta;
use Made\CmsPackage\Content\Data\PageMeta;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'app.name' => 'TTV Merwestad Dordrecht']);
});

it('describes a page with a title and a description of its own', function (): void {
    $page = Page::factory()->withMeta('Tafeltennis in Dordrecht: onze 14 teams', 'Van de jeugd tot de eredivisie.')->at('/teams')->create();

    expect(DescribePageMeta::run($page))->toEqual(new PageMeta(
        title: 'Tafeltennis in Dordrecht: onze 14 teams',
        description: 'Van de jeugd tot de eredivisie.',
        canonical: 'https://ttvmerwestad.nl/teams',
        indexable: true,
    ));
});

it('falls back to the name of the page and the site, and leaves the description to google', function (): void {
    $page = Page::create(['title' => 'Onze teams', 'slug' => 'teams']);
    $page->route()->create(['path' => '/teams']);

    expect(DescribePageMeta::run($page->refresh()))->toEqual(new PageMeta(
        title: 'Onze teams · TTV Merwestad Dordrecht',
        description: null,
        canonical: 'https://ttvmerwestad.nl/teams',
        indexable: true,
    ));
});

it('says when google may not show the page', function (): void {
    $page = Page::factory()->noindex()->at('/bedankt')->create();

    expect(DescribePageMeta::run($page)->indexable)->toBeFalse();
});

it('builds the canonical address from the url of the site, also in a subdirectory', function (): void {
    config(['app.url' => 'https://example.com/club/']);
    $home = Page::factory()->at('/')->create();
    $team = Page::factory()->at('/teams/jeugd')->create();

    expect(DescribePageMeta::run($home)->canonical)->toBe('https://example.com/club/')
        ->and(DescribePageMeta::run($team)->canonical)->toBe('https://example.com/club/teams/jeugd');
});

it('gives no canonical address to a page without an address', function (): void {
    $page = Page::factory()->create();

    expect(DescribePageMeta::run($page)->canonical)->toBeNull();
});

it('takes the prefix of the route group that serves the page into its canonical address', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create();
    $other = Page::factory()->published()->at('/teams')->create();

    Route::middleware('web')->prefix('nl')->group(function () use ($other): void {
        Route::madeCms(static fn (Page $page): array => [
            'served' => DescribePageMeta::run($page)->canonical,
            'other' => DescribePageMeta::run($other)->canonical,
        ]);
    });

    $this->get('nl/over-ons')->assertExactJson([
        'served' => 'https://ttvmerwestad.nl/nl/over-ons',
        'other' => 'https://ttvmerwestad.nl/teams',
    ]);

    expect(DescribePageMeta::run($page)->canonical)->toBe('https://ttvmerwestad.nl/over-ons');
});

it('keeps the host of the url of the site, whatever host the request names', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create();

    Route::middleware('web')->group(function (): void {
        Route::madeCms(static fn (Page $page): array => ['canonical' => DescribePageMeta::run($page)->canonical]);
    });

    $this->get('http://www.elders.example/over-ons')->assertExactJson(['canonical' => 'https://ttvmerwestad.nl/over-ons']);
});
