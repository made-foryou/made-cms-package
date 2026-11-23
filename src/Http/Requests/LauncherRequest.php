<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * A request from the launcher. Validates its input, translates it into the Data of an Access action
 * and turns a rejection by that action into a message under the field, in the launcher's language.
 */
abstract class LauncherRequest extends FormRequest
{
    /**
     * Who may do what is decided by the Access actions, never here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Refuses the request with a message under a field, back where a refusal of its validation goes, so a form that
     * goes back to its card (a fragment) does so for a refusal of the action too.
     *
     * @throws ValidationException
     */
    public function reject(string $field, string $error): never
    {
        throw ValidationException::withMessages([$field => $this->error($error)])->redirectTo($this->getRedirectUrl());
    }

    /**
     * The editor who sends the request, for the Actions that note who changed something. The routes that change
     * content open only for an editor; without one the session expired.
     *
     * @throws ValidationException
     */
    public function editorId(): int
    {
        return Visitor::requireEditor($this)->id;
    }

    protected function error(string $key): string
    {
        return (string) __('made-cms-package::launcher.errors.'.$key);
    }

    protected function login(): LoginSession
    {
        return LoginSession::of($this);
    }
}
