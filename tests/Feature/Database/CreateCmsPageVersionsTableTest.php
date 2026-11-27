<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

function pageVersionsMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_26_000000_create_cms_page_versions_table.php';
}

it('creates the table of the versions of a page and takes it away again', function (): void {
    $columns = ['page_id', 'number', 'strips', 'changes', 'published_by', 'published_at'];

    expect(Schema::hasColumns('cms_page_versions', $columns))->toBeTrue();

    pageVersionsMigration()->down();

    expect(Schema::hasTable('cms_page_versions'))->toBeFalse();

    pageVersionsMigration()->up();

    expect(Schema::hasColumns('cms_page_versions', $columns))->toBeTrue();
});
