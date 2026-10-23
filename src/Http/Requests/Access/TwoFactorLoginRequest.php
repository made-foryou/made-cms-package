<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Access;

use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Data\TwoFactorLogin;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The second step after a right password: a code from the authenticator app or a recovery code.
 */
final class TwoFactorLoginRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'digits:6'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.*' => $this->error('code_incomplete'),
            'recovery_code.*' => $this->error('recovery_invalid'),
        ];
    }

    /**
     * @throws ValidationException
     */
    public function toData(): TwoFactorLogin
    {
        $pending = $this->login()->pendingTwoFactor() ?? $this->reject('email', 'expired');

        return new TwoFactorLogin(
            editorId: $pending['editor'],
            code: $this->filled('code') ? $this->string('code')->toString() : null,
            recoveryCode: $this->filled('code') ? null : $this->string('recovery_code')->toString(),
            remember: $pending['remember'],
        );
    }

    /**
     * The message goes under whichever was typed: the code from the app or the recovery code.
     *
     * @throws ValidationException
     */
    public function rejectCode(): never
    {
        $this->filled('code') ? $this->reject('code', 'totp_invalid') : $this->reject('recovery_code', 'recovery_invalid');
    }
}
