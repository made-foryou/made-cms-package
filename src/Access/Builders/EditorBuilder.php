<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;

/**
 * @extends Builder<Editor>
 */
final class EditorBuilder extends Builder
{
    /**
     * E-mail addresses are stored trimmed and in lower case, so the typed address is matched the same way. The
     * database compares by its collation, which may treat two addresses as one: use it for whether an address is
     * taken, and firstWithEmail() for who an address is.
     */
    public function forEmail(string $email): self
    {
        return $this->where('email', mb_strtolower(trim($email)));
    }

    /**
     * The editor with exactly this address, trimmed and in lower case. A collation such as MySQL's
     * utf8mb4_unicode_ci ignores accents ("é" is "e", "ß" is "ss"), so the database may return the editor of another
     * address: whoever owns that address would get the editor's login. Its answer is compared once more here.
     */
    public function firstWithEmail(string $email): ?Editor
    {
        $email = mb_strtolower(trim($email));

        return $this->forEmail($email)
            ->get()
            ->first(static fn (Editor $editor): bool => mb_strtolower(trim($editor->email)) === $email);
    }

    /**
     * Editors who hold this role, next to any other roles.
     */
    public function withRole(Role $role): self
    {
        return $this->whereJsonContains('roles', $role->value);
    }

    /**
     * Every editor except this one.
     */
    public function otherThan(Editor $editor): self
    {
        return $this->whereKeyNot($editor->getKey());
    }

    /**
     * Editors whose last accepted authenticator code is older than this time step, or who never used one.
     */
    public function lastUsedTimestepBefore(int $timestep): self
    {
        return $this->where(fn (self $query): self => $query
            ->whereNull('two_factor_last_used_timestep')
            ->orWhere('two_factor_last_used_timestep', '<', $timestep));
    }

    /**
     * Editors whose stored recovery codes are still exactly these, so two requests can never use the same code.
     *
     * @param  list<string>  $hashes
     */
    public function withRecoveryCodes(array $hashes): self
    {
        return $this->where('two_factor_recovery_codes', json_encode($hashes));
    }
}
