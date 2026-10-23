<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Access;

use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * A login code for the address of step 1, and the page to come back to after the mail link.
 */
final class SendLoginCodeRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function toData(): LoginCodeRequest
    {
        [$email, $binding] = $this->login()->require();

        return new LoginCodeRequest(email: $email, binding: $binding, returnTo: $this->returnTo());
    }

    /**
     * Only a path on this site, so the mail link can never send the editor somewhere else.
     */
    private function returnTo(): ?string
    {
        $path = $this->input('return_to');

        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return null;
        }

        return $path;
    }
}
