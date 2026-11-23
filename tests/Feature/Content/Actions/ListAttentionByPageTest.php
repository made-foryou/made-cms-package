<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\ListAttentionByPage;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

it('gives what every page asks for attention by its id', function (): void {
    $teams = Page::factory()->published()->withMeta('Onze teams')->at('/teams')->create();
    $about = Page::factory()->published()->withMeta(null, 'Wie we zijn.')->at('/over-ons')->create();

    $attention = ListAttentionByPage::run();

    expect(array_map(static fn (AttentionItem $item): AttentionReason => $item->reason, $attention->of($teams->id)->items))
        ->toBe([AttentionReason::MetaDescriptionMissing])
        ->and($attention->of($about->id)->isEmpty())->toBeTrue();
});

it('leaves out that a page is still empty, which only its own screen points out', function (): void {
    $empty = Page::factory()->at('/jeugd')->create();

    expect(ListAttentionByPage::run()->of($empty->id)->isEmpty())->toBeTrue();
});

it('leaves out the deleted pages', function (): void {
    $page = Page::factory()->published()->at('/teams')->deletedAt(now())->create();

    expect(ListAttentionByPage::run()->of($page->id)->isEmpty())->toBeTrue();
});

it('reads the pages and their addresses once, however many pages there are', function (int $count): void {
    // Published, shown in Google and without a title of their own: the title Google gets needs the address.
    for ($i = 0; $i < $count; $i++) {
        Page::factory()->published()->at('/pagina-'.$i)->create();
    }

    DB::enableQueryLog();
    $attention = ListAttentionByPage::run();

    expect(DB::getQueryLog())->toHaveCount(2)
        ->and($attention->flagged())->toBe($count);
})->with([3, 30]);
