<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The addresses where visitors found no page: a dead door until a page or a redirect gets the address, or
        // until someone ignores it. The address without the prefix of a route group, as a redirect holds it.
        Schema::create('cms_not_found_paths', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            // Someone ignored it: it leaves the list, but its visits are still counted.
            $table->timestamp('ignored_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_not_found_paths');
    }
};
