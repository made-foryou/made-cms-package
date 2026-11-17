<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\EditExits;
use Made\CmsPackage\Http\Cms\SiteAddress;

it('gives the address of a page on the site and none for a page without one', function (): void {
    $page = Page::factory()->published()->at('/over-ons/team')->create();

    expect(SiteAddress::of($page))->toBe('/over-ons/team')
        ->and(SiteAddress::of(Page::factory()->create()))->toBeNull();
});

it('leaves for the site when visitors see the page, and for the pages screen when they do not', function (): void {
    $live = Page::factory()->published()->at('/over-ons')->create();
    $concept = Page::factory()->at('/concept')->create();
    $nameless = Page::factory()->create();

    expect(EditExits::of($live)->toArray())->toBe(['stop' => '/over-ons', 'publish' => '/over-ons'])
        ->and(EditExits::of($live)->stopsAtPages)->toBeFalse()
        ->and(EditExits::of($concept)->toArray())->toBe(['stop' => '/made-cms/pages/'.$concept->id, 'publish' => '/concept'])
        ->and(EditExits::of($concept)->stopsAtPages)->toBeTrue()
        ->and(EditExits::of($nameless)->toArray())->toBe(['stop' => '/made-cms/pages/'.$nameless->id, 'publish' => '/made-cms/pages/'.$nameless->id]);
});

it('keeps the base path of an app in a subdirectory', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create();

    app()->instance('request', Request::create('/sub/made-cms/start', server: [
        'SCRIPT_NAME' => '/sub/index.php',
        'SCRIPT_FILENAME' => '/var/www/sub/index.php',
        'PHP_SELF' => '/sub/index.php',
    ]));

    expect(EditExits::of($page)->toArray())->toBe(['stop' => '/sub/over-ons', 'publish' => '/sub/over-ons']);
});
