<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\AddStrip;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Actions\RebuildMediaUsage;
use Made\CmsPackage\Content\Actions\RemoveStrip;
use Made\CmsPackage\Content\Actions\UpdateStrip;
use Made\CmsPackage\Content\Data\RebuiltMediaUsage;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * Every row of the usage, without its id.
 *
 * @return list<string>
 */
function allUsageRows(): array
{
    $rows = MediaUsage::query()->get()
        ->map(static fn (MediaUsage $usage): string => implode(' ', [
            $usage->media_id, $usage->page_id, $usage->version->value, $usage->strip_id, $usage->strip_type, $usage->field,
        ]))
        ->sort()
        ->values()
        ->all();

    return array_values($rows);
}

it('builds the same usage from the strips as the Actions kept', function (): void {
    [$a, $b] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->create();
    $cards = AddStrip::run($page, 'cards', 0);
    UpdateStrip::run($page, $cards->id, ['kaarten' => [['titel' => 'Een', 'icoon' => $a->id], ['titel' => 'Twee', 'icoon' => $b->id]]], chooseLevels: true);
    PublishPage::run($page);
    RemoveStrip::run($page, $cards->id);
    $other = Page::factory()->create();
    $second = AddStrip::run($other, 'cards', 0);
    UpdateStrip::run($other, $second->id, ['kaarten' => [['titel' => 'Drie', 'icoon' => $a->id]]], chooseLevels: true);
    $other->delete();

    $kept = allUsageRows();
    MediaUsage::query()->delete();

    $rebuilt = RebuildMediaUsage::run();

    expect($kept)->toHaveCount(3)
        ->and(allUsageRows())->toBe($kept)
        ->and($rebuilt)->toEqual(new RebuiltMediaUsage(pages: 2, places: 3));
});

it('fills the usage of content written without the Actions, such as a seeder or an import', function (): void {
    $a = Media::factory()->create();
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => $a->id], id: 'h')])->create();

    RebuildMediaUsage::run();

    expect(allUsageRows())->toBe(["{$a->id} {$page->id} published h hero foto"]);
});

it('gives the share image of a page its place, also of a page in the bin', function (): void {
    [$a, $b] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->withShareImage($a)->create();
    $deleted = Page::factory()->withShareImage($b)->create();
    $deleted->delete();

    RebuildMediaUsage::run();

    expect(allUsageRows())->toBe(collect([
        "{$a->id} {$page->id} page   share_image",
        "{$b->id} {$deleted->id} page   share_image",
    ])->sort()->values()->all());
});

it('drops rows the strips no longer hold and keeps those of a strip whose type left the catalogue', function (): void {
    $a = Media::factory()->create();
    $page = Page::factory()->withStrips([storedStrip('oud', ['beeld' => $a->id], id: 'o')])->create();
    MediaUsage::factory()->for($a)->for($page)->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'beeld']);
    MediaUsage::factory()->for($a)->for($page)->create(['strip_id' => 'weg', 'strip_type' => 'hero', 'field' => 'foto']);

    RebuildMediaUsage::run();

    expect(allUsageRows())->toBe(["{$a->id} {$page->id} published o oud beeld"]);
});

it('counts a place in the draft and in the published version once, as the library does', function (): void {
    $a = Media::factory()->create();
    $strip = storedStrip('hero', ['foto' => $a->id], id: 'h');
    Page::factory()->published()->withStrips([$strip])->withDraft([$strip, storedStrip('hero', ['foto' => $a->id], id: 'n')])->create();

    $rebuilt = RebuildMediaUsage::run();

    expect(MediaUsage::query()->count())->toBe(3)
        ->and($rebuilt)->toEqual(new RebuiltMediaUsage(pages: 1, places: 2));
});

it('says so when there are no pages', function (): void {
    expect(RebuildMediaUsage::run())->toEqual(new RebuiltMediaUsage(pages: 0, places: 0));
});
