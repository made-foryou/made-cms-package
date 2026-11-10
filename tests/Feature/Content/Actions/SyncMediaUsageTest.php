<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\AddStrip;
use Made\CmsPackage\Content\Actions\DiscardDraft;
use Made\CmsPackage\Content\Actions\MoveStrip;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Actions\RemoveStrip;
use Made\CmsPackage\Content\Actions\RestoreStrip;
use Made\CmsPackage\Content\Actions\SetStripVisibility;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Actions\UpdateStrip;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * The places of the page as rows read: "version strip field media".
 *
 * @return list<string>
 */
function usageRows(Page $page): array
{
    $rows = MediaUsage::query()->onPage($page)->get()
        ->map(static fn (MediaUsage $usage): string => "{$usage->version->value} {$usage->strip_id} {$usage->field} {$usage->media_id}")
        ->sort()
        ->values()
        ->all();

    return array_values($rows);
}

/**
 * A cards strip with an icon in some of its cards.
 *
 * @param  list<int|null>  $icons  one card per entry, without an icon for null
 * @return array{id: string, type: string, visible: bool, values: array<string, mixed>}
 */
function cardsWithIcons(string $id, array $icons): array
{
    return storedStrip('cards', [
        'kaarten' => array_map(static fn (?int $icon): array => ['titel' => 'Kaart', 'icoon' => $icon], $icons),
    ], id: $id);
}

describe('through the Actions that change the draft', function (): void {
    it('records the places of the draft after saving a strip, an image in an item of a list too', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->create();
        $strip = AddStrip::run($page, 'cards', 0);

        UpdateStrip::run($page, $strip->id, ['kaarten' => [
            ['titel' => 'Een', 'icoon' => $a->id],
            ['titel' => 'Twee'],
            ['titel' => 'Drie', 'icoon' => $b->id],
        ]], chooseLevels: true);

        expect(usageRows($page))->toBe([
            "draft {$strip->id} kaarten.0.icoon {$a->id}",
            "draft {$strip->id} kaarten.2.icoon {$b->id}",
        ]);
    });

    it('moves the places to the published version after publishing', function (): void {
        $a = Media::factory()->create();
        $page = Page::factory()->withDraft([cardsWithIcons('s', [$a->id])])->create();

        PublishPage::run($page);

        expect(usageRows($page))->toBe(["published s kaarten.0.icoon {$a->id}"]);
    });

    it('keeps the published places of a strip removed from the draft until the draft is published', function (): void {
        $a = Media::factory()->create();
        $page = Page::factory()->published()->withStrips([cardsWithIcons('s', [$a->id])])->create();

        RemoveStrip::run($page, 's');

        expect(usageRows($page))->toBe(["published s kaarten.0.icoon {$a->id}"]);

        PublishPage::run($page);

        expect(usageRows($page))->toBe([]);
    });

    it('drops the places of a draft that is thrown away', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->published()
            ->withStrips([cardsWithIcons('s', [$a->id])])
            ->withDraft([cardsWithIcons('s', [$b->id])])
            ->create();

        UpdateStrip::run($page, 's', ['kaarten' => [['titel' => 'Kaart', 'icoon' => $b->id], ['titel' => 'Nieuw', 'icoon' => $a->id]]], chooseLevels: true);

        expect(usageRows($page))->toBe([
            "draft s kaarten.0.icoon {$b->id}",
            "draft s kaarten.1.icoon {$a->id}",
            "published s kaarten.0.icoon {$a->id}",
        ]);

        DiscardDraft::run($page);

        expect(usageRows($page))->toBe(["published s kaarten.0.icoon {$a->id}"]);
    });

    it('drops the places of a draft that changes nothing any more', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->published()->withStrips([cardsWithIcons('s', [$a->id])])->create();

        UpdateStrip::run($page, 's', ['kaarten' => [['titel' => 'Kaart', 'icoon' => $b->id]]], chooseLevels: true);
        UpdateStrip::run($page, 's', ['kaarten' => [['titel' => 'Kaart', 'icoon' => $a->id]]], chooseLevels: true);

        expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull()
            ->and(usageRows($page))->toBe(["published s kaarten.0.icoon {$a->id}"]);
    });

    it('counts a hidden strip, follows a move and brings the places of a strip back with it', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->withDraft([cardsWithIcons('s', [$a->id]), cardsWithIcons('t', [null, $b->id])])->create();

        SetStripVisibility::run($page, 's', false);
        MoveStrip::run($page, 't', 0);

        expect(usageRows($page))->toBe(["draft s kaarten.0.icoon {$a->id}", "draft t kaarten.1.icoon {$b->id}"]);

        $removed = RemoveStrip::run($page, 't');

        expect(usageRows($page))->toBe(["draft s kaarten.0.icoon {$a->id}"]);

        RestoreStrip::run($page, $removed, 1, chooseLevels: true);

        expect(usageRows($page))->toBe(["draft s kaarten.0.icoon {$a->id}", "draft t kaarten.1.icoon {$b->id}"]);
    });
});

it('builds the places of both versions from the strips of the page', function (): void {
    [$a, $b] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['foto' => $a->id], id: 'h'), cardsWithIcons('c', [$b->id, $a->id])])
        ->withDraft([storedStrip('hero', ['foto' => (string) $b->id], id: 'h')])
        ->create();

    SyncMediaUsage::run($page);

    expect(usageRows($page))->toBe([
        "draft h foto {$b->id}",
        "published c kaarten.0.icoon {$b->id}",
        "published c kaarten.1.icoon {$a->id}",
        "published h foto {$a->id}",
    ])->and(MediaUsage::query()->where('strip_id', 'h')->pluck('strip_type')->unique()->all())->toBe(['hero']);
});

it('writes only what changed, so a place that stays keeps its row', function (): void {
    [$a, $b] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->withStrips([cardsWithIcons('c', [$a->id, $b->id])])->create();
    SyncMediaUsage::run($page);
    $kept = MediaUsage::query()->where('field', 'kaarten.0.icoon')->value('id');

    $page->update(['strips' => [cardsWithIcons('c', [$a->id, $a->id])]]);
    SyncMediaUsage::run($page);

    expect(usageRows($page))->toBe(["published c kaarten.0.icoon {$a->id}", "published c kaarten.1.icoon {$a->id}"])
        ->and(MediaUsage::query()->where('field', 'kaarten.0.icoon')->value('id'))->toBe($kept);
});

it('drops the places a page no longer holds', function (): void {
    $page = Page::factory()->create();
    MediaUsage::factory()->for($page)->create(['strip_id' => 'weg', 'strip_type' => 'cards', 'field' => 'kaarten.0.icoon']);

    SyncMediaUsage::run($page);

    expect(usageRows($page))->toBe([]);
});

it('gives no place to the id of a file that is not in the library', function (): void {
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => 999_999], id: 'h')])->create();

    SyncMediaUsage::run($page);

    expect(usageRows($page))->toBe([]);
});

it('keeps the places of a strip whose type left the catalogue, and gives its copy in the draft the same places', function (): void {
    $a = Media::factory()->create();
    $strip = storedStrip('oud', ['beeld' => $a->id], id: 'o');
    $page = Page::factory()->withStrips([$strip])->create();
    MediaUsage::factory()->for($a)->for($page)->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'beeld']);

    $page->update(['draft_strips' => [$strip, cardsWithIcons('c', [$a->id])]]);
    SyncMediaUsage::run($page);

    expect(usageRows($page))->toBe([
        "draft c kaarten.0.icoon {$a->id}",
        "draft o beeld {$a->id}",
        "published o beeld {$a->id}",
    ]);
});

it('drops the places of a strip whose type left the catalogue once it is off the page', function (): void {
    $a = Media::factory()->create();
    $page = Page::factory()->withStrips([])->create();
    MediaUsage::factory()->for($a)->for($page)->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'beeld']);

    SyncMediaUsage::run($page);

    expect(usageRows($page))->toBe([]);
});

it('leaves the places of other pages alone', function (): void {
    $a = Media::factory()->create();
    $other = Page::factory()->withStrips([cardsWithIcons('c', [$a->id])])->create();
    SyncMediaUsage::run($other);

    SyncMediaUsage::run(Page::factory()->create());

    expect(usageRows($other))->toBe(["published c kaarten.0.icoon {$a->id}"]);
});
