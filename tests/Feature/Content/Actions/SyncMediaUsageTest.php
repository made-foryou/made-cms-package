<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Actions\AddStrip;
use Made\CmsPackage\Content\Actions\DeleteMedia;
use Made\CmsPackage\Content\Actions\DiscardDraft;
use Made\CmsPackage\Content\Actions\MoveStrip;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Actions\RemoveStrip;
use Made\CmsPackage\Content\Actions\RestoreStrip;
use Made\CmsPackage\Content\Actions\SetStripVisibility;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Actions\UpdateStrip;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Downloads;

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
 * A card of a cards strip in its stored form, with an id of its own.
 *
 * @return array{id: string, titel: string, icoon: int|null}
 */
function iconCard(?int $icon, ?string $id = null): array
{
    return ['id' => $id ?? (string) Str::uuid(), 'titel' => 'Kaart', 'icoon' => $icon];
}

/**
 * A cards strip in its stored form.
 *
 * @param  list<array<string, mixed>>  $cards
 * @return array{id: string, type: string, visible: bool, values: array<string, mixed>}
 */
function cardsStrip(string $id, array $cards): array
{
    return storedStrip('cards', ['kaarten' => $cards], id: $id);
}

/**
 * The ids of the cards of a strip in the stored draft.
 *
 * @return list<string>
 */
function draftCardIds(Page $page, string $stripId): array
{
    $stored = Page::query()->findOrFail($page->id);
    $cards = PageStrips::fromArray($stored->draft_strips ?? [])->find($stripId)->values['kaarten'] ?? [];

    return array_map(static fn (array $card): string => $card['id'], $cards);
}

describe('through the Actions that change the draft', function (): void {
    it('records the places of the draft after saving a strip, an image in an item of a list by the id of the item', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->create();
        $strip = AddStrip::run($page, 'cards', 0);

        UpdateStrip::run($page, $strip->id, ['kaarten' => [
            ['titel' => 'Een', 'icoon' => $a->id],
            ['titel' => 'Twee'],
            ['titel' => 'Drie', 'icoon' => $b->id],
        ]], chooseLevels: true);

        [$one, , $three] = draftCardIds($page, $strip->id);

        expect(usageRows($page))->toBe(collect([
            "draft {$strip->id} kaarten.{$one}.icoon {$a->id}",
            "draft {$strip->id} kaarten.{$three}.icoon {$b->id}",
        ])->sort()->values()->all());
    });

    it('records the place of a document in a file field, so the library keeps it', function (): void {
        app()->instance(StripTypes::class, new StripTypes([new Downloads]));
        $schema = Media::factory()->document()->create();
        $page = Page::factory()->create();
        $strip = AddStrip::run($page, 'downloads', 0);

        UpdateStrip::run($page, $strip->id, ['bestanden' => [['titel' => 'Schema', 'bestand' => $schema->id]]], chooseLevels: true);
        $item = PageStrips::fromArray(Page::query()->findOrFail($page->id)->draft_strips ?? [])->find($strip->id)->values['bestanden'][0]['id'];

        expect(usageRows($page))->toBe(["draft {$strip->id} bestanden.{$item}.bestand {$schema->id}"])
            ->and(DeleteMedia::run([$schema->id])->deleted)->toBe([])
            ->and(Media::query()->find($schema->id))->not->toBeNull();
    });

    it('moves the places to the published version after publishing', function (): void {
        $a = Media::factory()->create();
        $page = Page::factory()->withDraft([cardsStrip('s', [$card = iconCard($a->id)])])->create();

        PublishPage::run($page);

        expect(usageRows($page))->toBe(["published s kaarten.{$card['id']}.icoon {$a->id}"]);
    });

    it('keeps the published places of a strip removed from the draft until the draft is published', function (): void {
        $a = Media::factory()->create();
        $page = Page::factory()->published()->withStrips([cardsStrip('s', [$card = iconCard($a->id)])])->create();

        RemoveStrip::run($page, 's');

        expect(usageRows($page))->toBe(["published s kaarten.{$card['id']}.icoon {$a->id}"]);

        PublishPage::run($page);

        expect(usageRows($page))->toBe([]);
    });

    it('drops the places of a draft that is thrown away', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->published()->withStrips([cardsStrip('s', [$card = iconCard($a->id)])])->create();

        UpdateStrip::run($page, 's', ['kaarten' => [
            ['id' => $card['id'], 'titel' => 'Kaart', 'icoon' => $b->id],
            ['titel' => 'Nieuw', 'icoon' => $a->id],
        ]], chooseLevels: true);
        $added = draftCardIds($page, 's')[1];

        expect(usageRows($page))->toBe(collect([
            "draft s kaarten.{$card['id']}.icoon {$b->id}",
            "draft s kaarten.{$added}.icoon {$a->id}",
            "published s kaarten.{$card['id']}.icoon {$a->id}",
        ])->sort()->values()->all());

        DiscardDraft::run($page);

        expect(usageRows($page))->toBe(["published s kaarten.{$card['id']}.icoon {$a->id}"]);
    });

    it('drops the places of a draft that changes nothing any more', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->published()->withStrips([cardsStrip('s', [$card = iconCard($a->id)])])->create();

        UpdateStrip::run($page, 's', ['kaarten' => [[...$card, 'icoon' => $b->id]]], chooseLevels: true);
        UpdateStrip::run($page, 's', ['kaarten' => [[...$card, 'icoon' => $a->id]]], chooseLevels: true);

        expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull()
            ->and(usageRows($page))->toBe(["published s kaarten.{$card['id']}.icoon {$a->id}"]);
    });

    it('keeps the place of an image in an item that moves, so both versions hold the same place', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->published()->withStrips([cardsStrip('s', [$first = iconCard($a->id), $second = iconCard($b->id)])])->create();

        UpdateStrip::run($page, 's', ['kaarten' => [$second, $first]], chooseLevels: true);

        expect(usageRows($page))->toBe(collect([
            "draft s kaarten.{$first['id']}.icoon {$a->id}",
            "draft s kaarten.{$second['id']}.icoon {$b->id}",
            "published s kaarten.{$first['id']}.icoon {$a->id}",
            "published s kaarten.{$second['id']}.icoon {$b->id}",
        ])->sort()->values()->all());
    });

    it('counts a hidden strip, follows a move and brings the places of a strip back with it', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->withDraft([
            cardsStrip('s', [$one = iconCard($a->id)]),
            cardsStrip('t', [iconCard(null), $two = iconCard($b->id)]),
        ])->create();
        $rows = ["draft s kaarten.{$one['id']}.icoon {$a->id}", "draft t kaarten.{$two['id']}.icoon {$b->id}"];

        SetStripVisibility::run($page, 's', false);
        MoveStrip::run($page, 't', 0);

        expect(usageRows($page))->toBe($rows);

        $removed = RemoveStrip::run($page, 't');

        expect(usageRows($page))->toBe([$rows[0]]);

        RestoreStrip::run($page, $removed, 1, chooseLevels: true);

        expect(usageRows($page))->toBe($rows);
    });
});

it('builds the places of both versions from the strips of the page, an item without an id by its position', function (): void {
    [$a, $b] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->published()
        ->withStrips([
            storedStrip('hero', ['foto' => $a->id], id: 'h'),
            storedStrip('cards', ['kaarten' => [['icoon' => $b->id], ['icoon' => $a->id]]], id: 'c'),
        ])
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
    $page = Page::factory()->withStrips([cardsStrip('c', [$first = iconCard($a->id), $second = iconCard($b->id)])])->create();
    SyncMediaUsage::run($page);
    $kept = MediaUsage::query()->where('field', "kaarten.{$first['id']}.icoon")->value('id');

    $page->update(['strips' => [cardsStrip('c', [$first, [...$second, 'icoon' => $a->id]])]]);
    SyncMediaUsage::run($page);

    expect(usageRows($page))->toBe(collect([
        "published c kaarten.{$first['id']}.icoon {$a->id}",
        "published c kaarten.{$second['id']}.icoon {$a->id}",
    ])->sort()->values()->all())
        ->and(MediaUsage::query()->where('field', "kaarten.{$first['id']}.icoon")->value('id'))->toBe($kept);
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

describe('a strip whose type left the catalogue', function (): void {
    it('keeps its places, and its copy in the draft gets the same places', function (): void {
        $a = Media::factory()->create();
        $strip = storedStrip('oud', ['beeld' => $a->id], id: 'o');
        $page = Page::factory()->withStrips([$strip])->create();
        MediaUsage::factory()->for($a)->for($page)->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'beeld']);

        $page->update(['draft_strips' => [$strip, cardsStrip('c', [$card = iconCard($a->id)])]]);
        SyncMediaUsage::run($page);

        expect(usageRows($page))->toBe([
            "draft c kaarten.{$card['id']}.icoon {$a->id}",
            "draft o beeld {$a->id}",
            "published o beeld {$a->id}",
        ]);
    });

    it('holds the file its values hold after publishing, the file of the draft', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->published()
            ->withStrips([storedStrip('oud', ['beeld' => $a->id], id: 'o')])
            ->withDraft([storedStrip('oud', ['beeld' => $b->id], id: 'o')])
            ->create();
        MediaUsage::factory()->for($a)->for($page)->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'beeld']);
        MediaUsage::factory()->for($b)->for($page)->draft()->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'beeld']);

        PublishPage::run($page);

        expect(usageRows($page))->toBe(["published o beeld {$b->id}"]);
    });

    it('reads the file of an item of a list by the id of the item', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->published()
            ->withStrips([storedStrip('oud', ['fotos' => [['id' => 'x', 'foto' => $a->id]]], id: 'o')])
            ->withDraft([storedStrip('oud', ['fotos' => [['id' => 'y', 'foto' => null], ['id' => 'x', 'foto' => (string) $b->id]]], id: 'o')])
            ->create();
        MediaUsage::factory()->for($a)->for($page)->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'fotos.x.foto']);
        MediaUsage::factory()->for($b)->for($page)->draft()->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'fotos.x.foto']);

        PublishPage::run($page);

        expect(usageRows($page))->toBe(["published o fotos.x.foto {$b->id}"]);
    });

    it('drops its places once it is off the page', function (): void {
        $a = Media::factory()->create();
        $page = Page::factory()->withStrips([])->create();
        MediaUsage::factory()->for($a)->for($page)->create(['strip_id' => 'o', 'strip_type' => 'oud', 'field' => 'beeld']);

        SyncMediaUsage::run($page);

        expect(usageRows($page))->toBe([]);
    });
});

describe('the share image of a page', function (): void {
    it('gives the share image a place of the page itself, next to the places of its strips', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->withShareImage($b)->withStrips([storedStrip('hero', ['foto' => $a->id], id: 'h')])->create();

        SyncMediaUsage::run($page);

        expect(usageRows($page))->toBe([
            "page  share_image {$b->id}",
            "published h foto {$a->id}",
        ])->and(MediaUsage::query()->where('version', 'page')->value('strip_type'))->toBe('');
    });

    it('drops the place once the page has no share image of its own', function (): void {
        $page = Page::factory()->create();
        MediaUsage::factory()->for($page)->shareImage()->create();

        SyncMediaUsage::run($page);

        expect(usageRows($page))->toBe([]);
    });

    it('keeps the place when a strip of the page changes', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->withShareImage($b)->withStrips([storedStrip('hero', ['foto' => $a->id], id: 'h')])->create();
        SyncMediaUsage::run($page);

        SetStripVisibility::run($page, 'h', false);

        expect(usageRows($page))->toBe([
            "draft h foto {$a->id}",
            "page  share_image {$b->id}",
            "published h foto {$a->id}",
        ]);
    });

    it('keeps the place next to a strip whose type left the catalogue', function (): void {
        $a = Media::factory()->create();
        $page = Page::factory()->withShareImage($a)->withStrips([storedStrip('oud', ['beeld' => $a->id], id: '')])->create();
        MediaUsage::factory()->for($a)->for($page)->shareImage()->create();

        SyncMediaUsage::run($page);

        expect(usageRows($page))->toBe(["page  share_image {$a->id}"]);
    });

    it('gives no place to a share image that is not in the library', function (): void {
        $page = Page::factory()->create();
        $page->share_image_id = 999_999;

        SyncMediaUsage::run($page);

        expect(usageRows($page))->toBe([]);
    });

    it('reads the share image through the same shared lock as the files of the strips', function (): void {
        [$a, $b] = Media::factory()->count(2)->create()->all();
        $page = Page::factory()->withShareImage($a)->withStrips([storedStrip('hero', ['foto' => $b->id], id: 'h')])->create();
        $reads = [];
        $files = 'from '.quoted('cms_media');

        DB::listen(static function (QueryExecuted $query) use (&$reads, $files): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, $files)) {
                $reads[] = $query->sql;
            }
        });

        SyncMediaUsage::run($page);

        expect($reads)->toHaveCount(1)
            ->and($reads[0])->toContain('order by '.quoted('id').' asc')
            ->and(usageRows($page))->toHaveCount(2);
    });
});

it('leaves the places of other pages alone', function (): void {
    $a = Media::factory()->create();
    $other = Page::factory()->withStrips([cardsStrip('c', [$card = iconCard($a->id)])])->create();
    SyncMediaUsage::run($other);

    SyncMediaUsage::run(Page::factory()->create());

    expect(usageRows($other))->toBe(["published c kaarten.{$card['id']}.icoon {$a->id}"]);
});

it('reads the files it gives a place through the shared lock of the library, in the order of their ids', function (): void {
    [$a, $b] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => $b->id], id: 'h'), storedStrip('hero', ['foto' => $a->id], id: 'g')])->create();
    $reads = [];
    $files = 'from '.quoted('cms_media');

    DB::listen(static function (QueryExecuted $query) use (&$reads, $files): void {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, $files)) {
            $reads[] = $query->sql;
        }
    });

    SyncMediaUsage::run($page);

    expect($reads)->toHaveCount(1)
        ->and($reads[0])->toContain('order by '.quoted('id').' asc')
        ->and(usageRows($page))->toHaveCount(2);
});
