<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Content;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Models\AddressHit;

/**
 * One visit today of an address at the root without a page, typed by a person.
 *
 * @extends Factory<AddressHit>
 */
final class AddressHitFactory extends Factory
{
    protected $model = AddressHit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day' => now()->toImmutable()->startOfDay(),
            'prefix' => '',
            'path' => '/oud-adres',
            'outcome' => HitOutcome::NotFound,
            'source' => VisitSource::Direct,
            'referrer_path' => '',
            'hits' => 1,
        ];
    }

    public function at(string $path, string $prefix = ''): self
    {
        return $this->state(fn (): array => ['path' => $path, 'prefix' => $prefix]);
    }

    public function daysAgo(int $days): self
    {
        return $this->on(now()->toImmutable()->startOfDay()->subDays($days));
    }

    public function on(CarbonImmutable $day): self
    {
        return $this->state(fn (): array => ['day' => $day]);
    }

    public function outcome(HitOutcome $outcome): self
    {
        return $this->state(fn (): array => ['outcome' => $outcome]);
    }

    public function from(VisitSource $source, string $referrerPath = ''): self
    {
        return $this->state(fn (): array => ['source' => $source, 'referrer_path' => $referrerPath]);
    }

    public function hits(int $hits): self
    {
        return $this->state(fn (): array => ['hits' => $hits]);
    }
}
