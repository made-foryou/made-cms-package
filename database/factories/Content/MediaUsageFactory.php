<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

/**
 * A place in the published version of a page, in the field "foto" of a hero.
 *
 * @extends Factory<MediaUsage>
 */
final class MediaUsageFactory extends Factory
{
    protected $model = MediaUsage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_id' => Media::factory(),
            'page_id' => Page::factory(),
            'version' => UsageVersion::Published,
            'strip_id' => (string) Str::uuid(),
            'strip_type' => 'hero',
            'field' => 'foto',
        ];
    }

    public function draft(): self
    {
        return $this->state(fn (): array => ['version' => UsageVersion::Draft]);
    }
}
