<?php

declare(strict_types=1);

namespace Made\CmsPackage\Database\Factories\Access;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => mb_strtolower(fake()->unique()->safeEmail()),
            'password' => self::PASSWORD,
            'remember_token' => Str::random(10),
        ];
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
}
