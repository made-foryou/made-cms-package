<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per place a file of the media library is used: a field of a strip in a version of a page. The
        // Content Actions keep it in step with cms_pages; made-cms:media-usage builds it again from there.
        Schema::create('cms_media_usages', function (Blueprint $table): void {
            $table->id();
            // A file in use is never deleted: deleting it while a row points at it fails.
            $table->foreignId('media_id')->constrained('cms_media')->restrictOnDelete();
            // A deleted page keeps its rows until it is removed for good.
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            // "draft" for draft_strips, "published" for strips.
            $table->string('version', 16);
            $table->string('strip_id', 64);
            // The key of the strip type, so the place is named from the catalogue without reading the strips.
            $table->string('strip_type', 100);
            // The path of the field in the strip: "foto", or "kaarten.2.foto" in the third item of a list.
            $table->string('field');
            $table->unique(['page_id', 'version', 'strip_id', 'field'], 'cms_media_usages_place_unique');
            $table->index(['media_id', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media_usages');
    }
};
