<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\MakeGoogleTitle;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

it('gives the title of the page itself as the whole title', function (): void {
    $page = Page::factory()->at('/teams')->create(['title' => 'Onze teams', 'meta_title' => 'Teams van TTV Merwestad']);

    expect(MakeGoogleTitle::run($page))->toBe('Teams van TTV Merwestad');
});

it('gives the name of the page and the site without a title of its own', function (): void {
    $page = Page::factory()->at('/teams')->create(['title' => 'Onze teams']);

    expect(MakeGoogleTitle::run($page))->toBe('Onze teams · TTV Merwestad');
});
