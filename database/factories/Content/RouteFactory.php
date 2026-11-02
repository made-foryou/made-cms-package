<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Illuminate\Database\Eloquent\Factories\Factory;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

/**
 * @extends Factory<Route>
 */
final class RouteFactory extends Factory
{
    protected $model = Route::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => PagePath::fromString(fake()->unique()->slug(2))->value,
            'routable_type' => (new Page)->getMorphClass(),
            'routable_id' => Page::factory(),
        ];
    }

    public function at(string $path): self
    {
        return $this->state(fn (): array => ['path' => PagePath::fromString($path)->value]);
    }
}
