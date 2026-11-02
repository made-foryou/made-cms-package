<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

/**
 * @extends Factory<Page>
 *
 * @phpstan-import-type StoredStrip from Strip
 */
final class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => PageStatus::Draft,
            'strips' => [],
            'draft_strips' => null,
            'published_at' => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'status' => PageStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Gives the page its address on the site, e.g. "/" or "/over-ons/team".
     */
    public function at(string $path): self
    {
        return $this->has(RouteFactory::new()->at($path), 'route');
    }

    /**
     * @param  list<StoredStrip>  $strips  the published strips in their stored form
     */
    public function withStrips(array $strips): self
    {
        return $this->state(fn (): array => ['strips' => $strips]);
    }

    /**
     * @param  list<StoredStrip>  $strips  the draft strips in their stored form
     */
    public function withDraft(array $strips): self
    {
        return $this->state(fn (): array => ['draft_strips' => $strips]);
    }
}
