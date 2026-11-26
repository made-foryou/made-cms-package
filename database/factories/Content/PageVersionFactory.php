<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Illuminate\Database\Eloquent\Factories\Factory;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

/**
 * The first publication of a page without strips, by an editor that is not known.
 *
 * @phpstan-import-type StoredStrip from Strip
 *
 * @extends Factory<PageVersion>
 */
final class PageVersionFactory extends Factory
{
    protected $model = PageVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'number' => 1,
            'strips' => [],
            'changes' => ['first' => true, 'added' => [], 'changed' => [], 'moved' => [], 'removed' => []],
            'published_by' => null,
            'published_at' => now(),
        ];
    }

    public function number(int $number): self
    {
        return $this->state(fn (): array => ['number' => $number]);
    }

    /**
     * @param  list<StoredStrip>  $strips
     */
    public function withStrips(array $strips): self
    {
        return $this->state(fn (): array => ['strips' => $strips]);
    }

    public function publishedBy(Editor|int $editor): self
    {
        return $this->state(fn (): array => ['published_by' => $editor instanceof Editor ? $editor->id : $editor]);
    }
}
