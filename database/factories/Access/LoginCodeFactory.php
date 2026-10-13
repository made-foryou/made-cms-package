<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Access;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Made\CmsPackage\Access\Models\LoginCode;

/**
 * @extends Factory<LoginCode>
 */
final class LoginCodeFactory extends Factory
{
    protected $model = LoginCode::class;

    /**
     * The plain code behind the default hash, for tests that verify a code.
     */
    public const string CODE = '123456';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'code_hash' => Hash::make(self::CODE),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function consumed(): self
    {
        return $this->state(fn (): array => [
            'consumed_at' => now()->subMinute(),
        ]);
    }
}
