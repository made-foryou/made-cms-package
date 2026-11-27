<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->travelTo(CarbonImmutable::parse('2026-11-28 09:00:10'));
});

it('publishes the pages whose moment came and says how many', function (): void {
    $due = Page::factory()->withDraft([storedStrip('cards', ['kaarten' => []], id: 'a')])->scheduledAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create();
    $later = Page::factory()->withDraft([storedStrip('cards', ['kaarten' => []], id: 'a')])->scheduledAt(CarbonImmutable::parse('2026-11-28 09:01:00'))->create();

    $this->artisan('made-cms:publish-scheduled')
        ->expectsOutputToContain('1 geplande pagina gepubliceerd.')
        ->expectsOutputToContain('Geen geplande publicaties tegengehouden.')
        ->assertSuccessful();

    expect($due->refresh()->status)->toBe(PageStatus::Published)
        ->and($later->refresh()->status)->toBe(PageStatus::Draft);
});

it('says how many publications a strip stopped', function (): void {
    Page::factory()->withDraft([storedStrip('hero', ['kop' => null], id: 'a')])->scheduledAt(CarbonImmutable::parse('2026-11-28 08:00:00'))->create();
    Page::factory()->withDraft([storedStrip('hero', ['kop' => null], id: 'a')])->scheduledAt(CarbonImmutable::parse('2026-11-28 08:30:00'))->create();

    $this->artisan('made-cms:publish-scheduled')
        ->expectsOutputToContain('Geen geplande pagina’s gepubliceerd.')
        ->expectsOutputToContain('2 geplande publicaties tegengehouden door een strook: die pagina’s vragen aandacht.')
        ->assertSuccessful();
});
