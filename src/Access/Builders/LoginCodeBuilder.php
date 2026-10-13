<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Access\Models\LoginCode;

/**
 * @extends Builder<LoginCode>
 */
final class LoginCodeBuilder extends Builder
{
    /**
     * Codes that have not been used and have not expired yet.
     */
    public function active(): self
    {
        return $this
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    public function forEmail(string $email): self
    {
        return $this->where('email', $email);
    }
}
