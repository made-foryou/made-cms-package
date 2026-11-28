<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How often an address the package answers was asked for, per day: counts only, never who asked. No ip address,
        // user agent, cookie or moment of a single visit is kept; the user agent only decides the source.
        Schema::create('cms_address_hits', function (Blueprint $table): void {
            $table->id();
            // The day in the timezone of the app.
            $table->date('day');
            // The prefix of the route group of Route::madeCms() without slashes, "" at the root, and the address in it.
            $table->string('prefix', 64)->default('');
            $table->string('path');
            // not_found, redirected, served (sitemap.xml, robots.txt) or page (a crawler only).
            $table->string('outcome', 16);
            // google, search_engine, internal, external, direct, googlebot or bot.
            $table->string('source', 16);
            // The address of the page of the site that linked here, "" for any other source: a null never meets
            // another in a unique index, so the count of a day would split into rows.
            $table->string('referrer_path')->default('');
            $table->unsignedInteger('hits');
            $table->unique(['path', 'prefix', 'day', 'outcome', 'source', 'referrer_path'], 'cms_address_hits_tally_unique');
            $table->index(['day', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_address_hits');
    }
};
