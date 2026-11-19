<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Carbon\CarbonInterface;
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
            'parent_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'position' => 0,
            'in_menu' => true,
            'meta_title' => null,
            'meta_description' => null,
            'in_sitemap' => true,
            'indexable' => true,
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
     * Puts the page under a parent; give it its address with at() as well.
     */
    public function under(Page $parent): self
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    public function atPosition(int $position): self
    {
        return $this->state(fn (): array => ['position' => $position]);
    }

    public function inMenu(bool $inMenu = true): self
    {
        return $this->state(fn (): array => ['in_menu' => $inMenu]);
    }

    /**
     * Gives the page a title and a description in Google of its own.
     */
    public function withMeta(?string $title, ?string $description = null): self
    {
        return $this->state(fn (): array => ['meta_title' => $title, 'meta_description' => $description]);
    }

    /**
     * Keeps the page out of Google: noindex.
     */
    public function noindex(): self
    {
        return $this->state(fn (): array => ['indexable' => false]);
    }

    public function outOfSitemap(): self
    {
        return $this->state(fn (): array => ['in_sitemap' => false]);
    }

    /**
     * Deletes the page at a moment. A deleted page has no address, so never combine this with at().
     */
    public function deletedAt(CarbonInterface $moment): self
    {
        return $this->state(fn (): array => ['deleted_at' => $moment]);
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
