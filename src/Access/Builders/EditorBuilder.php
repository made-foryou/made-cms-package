<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Access\Models\Editor;

/**
 * @extends Builder<Editor>
 */
final class EditorBuilder extends Builder
{
    /**
     * E-mail addresses are stored trimmed and in lower case, so the typed address is matched the same way.
     */
    public function forEmail(string $email): self
    {
        return $this->where('email', mb_strtolower(trim($email)));
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
}
