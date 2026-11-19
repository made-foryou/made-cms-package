<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\MakeFallbackTitle;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'app.name' => 'TTV Merwestad Dordrecht']);
});

it('names a page after itself and the site', function (): void {
    $page = Page::factory()->at('/teams')->create(['title' => 'Onze teams']);

    expect(MakeFallbackTitle::run($page))->toBe('Onze teams · TTV Merwestad Dordrecht');
});

it('names the home page after the site alone', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home']);

    expect(MakeFallbackTitle::run($home))->toBe('TTV Merwestad Dordrecht');
});

it('takes the host as the name of a site that kept the name of laravel', function (): void {
    config(['app.name' => 'Laravel']);
    $page = Page::factory()->at('/teams')->create(['title' => 'Onze teams']);

    expect(MakeFallbackTitle::run($page))->toBe('Onze teams · ttvmerwestad.nl');
});
