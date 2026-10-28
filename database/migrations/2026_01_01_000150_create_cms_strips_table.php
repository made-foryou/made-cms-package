<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_strips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('position');
            $table->json('content');
            $table->timestamps();

            $table->index(['page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_strips');
    }
};
