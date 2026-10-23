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

    /**
     * Codes that were asked for in the browser with this binding.
     */
    public function boundTo(string $binding): self
    {
        return $this->where('session_hash', hash('sha256', $binding));
    }

    /**
     * The code whose mail contains this link token.
     */
    public function forLink(string $token): self
    {
        return $this->where('link_hash', hash('sha256', $token));
    }

    public function withAttemptsBelow(int $max): self
    {
        return $this->where('attempts', '<', $max);
    }

    public function latestFirst(): self
    {
        return $this->orderByDesc('id');
    }
}
