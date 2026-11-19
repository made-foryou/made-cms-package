<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

function sitemapsMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_19_000200_create_cms_sitemaps_table.php';
}

it('creates the table of the built sitemaps and takes it away again', function (): void {
    $columns = ['prefix', 'xml', 'address_count', 'errors', 'cause', 'page_id', 'editor_id', 'built_at'];

    expect(Schema::hasColumns('cms_sitemaps', $columns))->toBeTrue();

    sitemapsMigration()->down();

    expect(Schema::hasTable('cms_sitemaps'))->toBeFalse();

    sitemapsMigration()->up();

    expect(Schema::hasColumns('cms_sitemaps', $columns))->toBeTrue();
});
