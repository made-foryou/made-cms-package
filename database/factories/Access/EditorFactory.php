<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Access;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;

/**
 * @extends Factory<Editor>
 */
final class EditorFactory extends Factory
{
    protected $model = Editor::class;

    /**
     * The plain password behind the default hash, for tests that log in with a password.
     */
    public const string PASSWORD = 'password';

    /**
     * The authenticator secret and recovery codes of the withTwoFactor() state.
     */
    public const string TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public const array RECOVERY_CODES = ['herstel-code-een', 'herstel-code-twee'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => mb_strtolower(fake()->unique()->safeEmail()),
            'password' => self::PASSWORD,
            'roles' => [Role::ContentManager],
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An editor who holds exactly these roles.
     */
    public function withRoles(Role ...$roles): self
    {
        return $this->state(fn (): array => [
            'roles' => array_values($roles),
        ]);
    }

    /**
     * An editor who only logs in with a code from the mail.
     */
    public function withoutPassword(): self
    {
        return $this->state(fn (): array => [
            'password' => null,
        ]);
    }

    /**
     * An editor who confirmed an authenticator app.
     */
    public function withTwoFactor(): self
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => self::TWO_FACTOR_SECRET,
            'two_factor_recovery_codes' => array_map(fn (string $code): string => hash('sha256', $code), self::RECOVERY_CODES),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_timestep' => null,
        ]);
    }
}
