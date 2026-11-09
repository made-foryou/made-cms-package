<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Hashing;

use Closure;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

/**
 * Wraps a real hasher and counts how often a hash is made and checked, to prove equal work on every path.
 * The real hasher is resolved on every call, so it follows a change of the hash settings.
 */
final class CountingHasher implements Hasher
{
    public int $checks = 0;

    public int $makes = 0;

    /**
     * @param  Closure(): Hasher  $hasher
     */
    public function __construct(
        private readonly Closure $hasher,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function make(#[SensitiveParameter] mixed $value, array $options = []): string
    {
        $this->makes++;

        return ($this->hasher)()->make($value, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function check(#[SensitiveParameter] mixed $value, mixed $hashedValue, array $options = []): bool
    {
        $this->checks++;

        return ($this->hasher)()->check($value, $hashedValue, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function needsRehash(mixed $hashedValue, array $options = []): bool
    {
        return ($this->hasher)()->needsRehash($hashedValue, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function info(mixed $hashedValue): array
    {
        return ($this->hasher)()->info($hashedValue);
    }

    public function reset(): void
    {
        $this->checks = 0;
        $this->makes = 0;
    }
}
