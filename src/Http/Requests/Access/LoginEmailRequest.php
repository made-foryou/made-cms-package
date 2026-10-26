<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Access;

use Made\CmsPackage\Access\Data\LoginStart;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Step 1: the e-mail address, with what this device remembers about earlier logins.
 */
final class LoginEmailRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => $this->error('email_required'),
            'email.*' => $this->error('email_invalid'),
        ];
    }

    public function toData(): LoginStart
    {
        return new LoginStart(
            email: mb_strtolower(trim($this->string('email')->toString())),
            device: EditorDevice::read($this),
        );
    }
}
