<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Access;

use Made\CmsPackage\Access\Data\PasswordLogin;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The password for the address of step 1, and whether to remember this device.
 */
final class PasswordLoginRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.*' => $this->error('password_required'),
        ];
    }

    public function toData(): PasswordLogin
    {
        [$email] = $this->login()->require();

        return new PasswordLogin(
            email: $email,
            password: $this->string('password')->toString(),
            remember: $this->boolean('remember'),
        );
    }
}
