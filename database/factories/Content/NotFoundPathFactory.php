<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Made\CmsPackage\Content\Models\NotFoundPath;

/**
 * An address without a page, first and last seen just now, not ignored.
 *
 * @extends Factory<NotFoundPath>
 */
final class NotFoundPathFactory extends Factory
{
    protected $model = NotFoundPath::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => '/'.fake()->unique()->slug(2),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'ignored_at' => null,
        ];
    }

    public function at(string $path): self
    {
        return $this->state(fn (): array => ['path' => $path]);
    }

    public function seen(CarbonImmutable $first, ?CarbonImmutable $last = null): self
    {
        return $this->state(fn (): array => ['first_seen_at' => $first, 'last_seen_at' => $last ?? $first]);
    }

    public function ignoredAt(CarbonImmutable $moment): self
    {
        return $this->state(fn (): array => ['ignored_at' => $moment]);
    }
}
