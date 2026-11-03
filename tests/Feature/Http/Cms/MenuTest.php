<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\Menu;

/**
 * @return list<array{label: string, url: string, current: bool, depth: int}>
 */
function siteItems(?Screen $current = null, ?Page $page = null): array
{
    return Menu::groups([Screen::Pages, Screen::News], $current, ListPages::run(), $page)[0]['items'];
}

it('lists the overview and the pages of the menu under the site', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);
    Page::factory()->atPosition(2)->inMenu(false)->at('/bedankt')->create(['title' => 'Bedankt']);

    expect(siteItems())->toBe([
        ['label' => 'Alle pagina’s', 'url' => '/made-cms/pages', 'current' => false, 'depth' => 0],
        ['label' => 'Home', 'url' => '/made-cms/pages/'.$home->id, 'current' => false, 'depth' => 0],
        ['label' => 'Over ons', 'url' => '/made-cms/pages/'.$about->id, 'current' => false, 'depth' => 0],
        ['label' => 'Team', 'url' => '/made-cms/pages/'.$team->id, 'current' => false, 'depth' => 1],
    ]);
});

it('marks the overview as current on the pages screen', function (): void {
    Page::factory()->at('/')->create(['title' => 'Home']);

    expect(array_column(siteItems(Screen::Pages), 'current'))->toBe([true, false]);
});

it('marks the page as current on its own screen', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create(['title' => 'Home']);
    Page::factory()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons']);

    expect(array_column(siteItems(Screen::Pages, $home), 'current'))->toBe([false, true, false]);
});

it('marks the overview as current for a page outside the menu', function (): void {
    $hidden = Page::factory()->inMenu(false)->at('/bedankt')->create();

    expect(array_column(siteItems(Screen::Pages, $hidden), 'current'))->toBe([true]);
});

it('lists the screen itself without the pages', function (): void {
    expect(Menu::groups([Screen::Pages], Screen::Pages)[0]['items'])->toBe([
        ['label' => 'Pagina’s', 'url' => '/made-cms/pages', 'current' => true, 'depth' => 0],
    ]);
});
