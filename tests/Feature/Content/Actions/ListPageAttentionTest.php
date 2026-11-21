<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Actions\ListPageAttention;
use Made\CmsPackage\Content\Attention\AttentionSource;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\AttentionSources;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

/**
 * A source that gives the same items for every page.
 */
function fixedAttentionSource(AttentionItem ...$items): AttentionSource
{
    return new readonly class(array_values($items)) implements AttentionSource
    {
        /**
         * @param  list<AttentionItem>  $items
         */
        public function __construct(private array $items) {}

        public function items(Page $page): array
        {
            return $this->items;
        }
    };
}

/**
 * @return list<AttentionReason>
 */
function attentionReasons(Page $page): array
{
    return array_map(static fn (AttentionItem $item): AttentionReason => $item->reason, ListPageAttention::run($page)->items);
}

it('gives an empty list for a page that asks for nothing', function (): void {
    $page = Page::factory()->published()->at('/teams')->withStrips([storedStrip()])
        ->create(['meta_title' => 'Onze teams', 'meta_description' => 'Alle teams van de club.']);

    $attention = ListPageAttention::run($page);

    expect($attention->items)->toBe([])
        ->and($attention->isEmpty())->toBeTrue()
        ->and($attention->count())->toBe(0)
        ->and($attention->urgent())->toBe(0);
});

it('lists what a page asks for, the urgent first', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00:00'));
    $page = Page::factory()->published()->at('/locatie')->withStrips([storedStrip(id: 'a')])
        ->withDraft([storedStrip(id: 'b')])
        ->draftSince(CarbonImmutable::parse('2026-11-14 12:00:00'))
        ->create(['meta_title' => str_repeat('a', 68)]);

    $attention = ListPageAttention::run($page);

    expect(attentionReasons($page))->toBe([
        AttentionReason::StaleDraft,
        AttentionReason::MetaDescriptionMissing,
        AttentionReason::MetaTitleTooLong,
    ])
        ->and($attention->count())->toBe(3)
        ->and($attention->urgent())->toBe(2);
});

it('says an empty page that was never published is empty', function (): void {
    expect(attentionReasons(Page::factory()->at('/jeugd')->create()))->toBe([AttentionReason::Empty]);
});

it('takes the sources of the container and keeps their order within a severity', function (): void {
    app()->instance(AttentionSources::class, new AttentionSources([
        fixedAttentionSource(
            new AttentionItem(AttentionReason::Empty, AttentionSeverity::Low),
            new AttentionItem(AttentionReason::MetaTitleTooLong, AttentionSeverity::Medium),
        ),
        fixedAttentionSource(
            new AttentionItem(AttentionReason::StaleDraft, AttentionSeverity::Medium),
            new AttentionItem(AttentionReason::MetaDescriptionMissing, AttentionSeverity::High),
        ),
    ]));

    expect(attentionReasons(Page::factory()->create()))->toBe([
        AttentionReason::MetaDescriptionMissing,
        AttentionReason::MetaTitleTooLong,
        AttentionReason::StaleDraft,
        AttentionReason::Empty,
    ]);
});
