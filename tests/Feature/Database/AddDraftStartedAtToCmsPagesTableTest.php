<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function draftStartedAtMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_21_000100_add_draft_started_at_to_cms_pages_table.php';
}

/**
 * Stores a page the way a host had it before the column, with or without a draft.
 */
function pageBeforeDraftStart(string $slug, ?string $draft, string $updatedAt): int
{
    return DB::table('cms_pages')->insertGetId([
        'title' => ucfirst($slug),
        'slug' => $slug,
        'strips' => '[]',
        'draft_strips' => $draft,
        'created_at' => '2026-01-01 09:00:00',
        'updated_at' => $updatedAt,
    ]);
}

it('adds when the draft of a page started and takes it away again', function (): void {
    draftStartedAtMigration()->down();

    expect(Schema::hasColumn('cms_pages', 'draft_started_at'))->toBeFalse();

    draftStartedAtMigration()->up();

    expect(Schema::hasColumn('cms_pages', 'draft_started_at'))->toBeTrue();
});

it('starts a draft that was there already at the last change of its page', function (): void {
    draftStartedAtMigration()->down();
    $withDraft = pageBeforeDraftStart('over-ons', '[]', '2026-11-10 14:30:00');
    $withoutDraft = pageBeforeDraftStart('contact', null, '2026-11-12 08:00:00');

    draftStartedAtMigration()->up();

    expect(DB::table('cms_pages')->find($withDraft)?->draft_started_at)->toStartWith('2026-11-10 14:30:00')
        ->and(DB::table('cms_pages')->find($withoutDraft)?->draft_started_at)->toBeNull();
});

it('leaves a database that has the column already as it is', function (): void {
    draftStartedAtMigration()->up();

    expect(Schema::hasColumn('cms_pages', 'draft_started_at'))->toBeTrue();
});
