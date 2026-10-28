<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Illuminate\Database\Eloquent\Factories\Factory;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;

/**
 * @extends Factory<Strip>
 */
final class StripFactory extends Factory
{
    protected $model = Strip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'type' => 'hero',
            'position' => 0,
            'content' => ['title' => fake()->sentence(3)],
        ];
    }
}
