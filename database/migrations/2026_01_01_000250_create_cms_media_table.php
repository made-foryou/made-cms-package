<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per file of the media library; strips store its id, so a file changes everywhere it is used.
        Schema::create('cms_media', function (Blueprint $table): void {
            $table->id();
            // The folder of the file on the media disk, media/{ulid}, which holds every version of it; also the key of
            // the link that always leads to the file as it is now, so it never changes.
            $table->char('ulid', 26)->unique();
            // A folder keeps its files: deleting one that still holds files fails.
            $table->foreignId('folder_id')->nullable()->constrained('cms_media_folders')->restrictOnDelete();
            $table->string('kind')->index();
            // The slug of the file name without extension, the name search engines read.
            $table->string('name');
            // The file the site shows, on the media disk.
            $table->string('path');
            // The upload as it came in, next to a photo that was scaled down; null when the file was kept as it is.
            $table->string('original_path')->nullable();
            // The small picture of a photo that was scaled down, for the tiles of the CMS; null for any other file.
            $table->string('thumb_path')->nullable();
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            // The upright size of the original of a photo that was scaled down, the image a crop is cut from; null for
            // a file kept as it came in.
            $table->unsignedInteger('original_width')->nullable();
            $table->unsignedInteger('original_height')->nullable();
            // One alt text for every place the file is used; for a document the description of its download link.
            $table->string('alt')->nullable();
            // The point a crop keeps in view, from 0 to 1 from the left and the top.
            $table->decimal('focal_x', 5, 4)->nullable();
            $table->decimal('focal_y', 5, 4)->nullable();
            // How the photo was cut from its original: {x, y, width, height, rotation, flip, scale_down}, as the editor
            // saw it (CropArea); null for the whole original, not turned and scaled down.
            $table->json('crop')->nullable();
            // The number of the version the site shows: 1 for the upload, each replacement the highest so far plus one.
            $table->unsignedInteger('version')->default(1);
            // The version that waits to be put back (MediaVersion): the files and columns the file had before it was
            // replaced, or before the version it had was put back; null when nothing waits.
            $table->json('previous')->nullable();
            // The editor who uploaded the file; the file stays when the editor is removed.
            $table->foreignId('uploaded_by')->nullable()->constrained('cms_editors')->nullOnDelete();
            // Who placed the version the site shows, and when, by replacing the file; null for the upload itself.
            $table->foreignId('replaced_by')->nullable()->constrained('cms_editors')->nullOnDelete();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_media');
    }
};
