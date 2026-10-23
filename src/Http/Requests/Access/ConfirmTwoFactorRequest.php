<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Access;

use Made\CmsPackage\Access\Data\TwoFactorConfirmation;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The first code from the new authenticator app.
 */
final class ConfirmTwoFactorRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
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

    public function toData(): TwoFactorConfirmation
    {
        return new TwoFactorConfirmation(Visitor::requireEditor($this), $this->string('code')->toString());
    }
}
