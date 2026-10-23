<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Session;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The login in progress in this browser: the typed address and a random binding that ties codes
 * and mail links to this browser. Transport state only; every rule lives in the Access actions.
 */
final readonly class LoginSession
{
    private const string KEY = 'made-cms-package.login';

    public function __construct(
        private Session $session,
    ) {}

    public static function of(Request $request): self
    {
        return new self($request->session());
    }

    public function start(string $email): void
    {
        $this->session->put(self::KEY, [
            'email' => mb_strtolower(trim($email)),
            'binding' => Str::random(40),
        ]);
    }

    public function email(): ?string
    {
        $email = $this->session->get(self::KEY.'.email');

        return is_string($email) ? $email : null;
    }

    public function binding(): ?string
    {
        $binding = $this->session->get(self::KEY.'.binding');

        return is_string($binding) ? $binding : null;
    }

    /**
     * The address and binding of the login in progress, or a message that the editor has to start again.
     *
     * @return array{string, string}
     *
     * @throws ValidationException
     */
    public function require(): array
    {
        $email = $this->email();
        $binding = $this->binding();

        if ($email === null || $binding === null) {
            throw ValidationException::withMessages([
                'email' => __('made-cms-package::launcher.errors.expired'),
            ]);
        }

        return [$email, $binding];
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }
}
