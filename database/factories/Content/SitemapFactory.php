<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Illuminate\Database\Eloquent\Factories\Factory;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Sitemap;

/**
 * A sitemap of the root group without addresses, built by the command just now.
 *
 * @extends Factory<Sitemap>
 */
final class SitemapFactory extends Factory
{
    protected $model = Sitemap::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prefix' => '',
            'xml' => '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".'</urlset>'."\n",
            'address_count' => 0,
            'errors' => [],
            'cause' => SitemapCause::Command,
            'built_at' => now(),
        ];
    }
}
