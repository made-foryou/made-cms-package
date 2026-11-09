<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Access;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Made\CmsPackage\Access\Actions\IssueLoginCode;
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
     * The plain browser binding and link token behind the default hashes.
     */
    public const string BINDING = 'browser-binding';

    public const string LINK = 'link-token';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'code_hash' => IssueLoginCode::make()->codeHash(self::CODE),
            'attempts' => 0,
            'session_hash' => hash('sha256', self::BINDING),
            'link_hash' => hash('sha256', self::LINK.'-'.Str::random(8)),
            'return_to' => null,
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
