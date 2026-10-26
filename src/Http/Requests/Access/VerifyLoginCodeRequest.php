<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Access;

use Made\CmsPackage\Access\Data\CodeLogin;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The code from the mail, typed over in the browser that asked for it.
 */
final class VerifyLoginCodeRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:'.config()->integer('made-cms-package.login_code.length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.*' => $this->error('code_incomplete'),
        ];
    }

    public function toData(): CodeLogin
    {
        [$email, $binding] = $this->login()->require();

        return new CodeLogin(email: $email, code: $this->string('code')->toString(), binding: $binding);
    }
}
