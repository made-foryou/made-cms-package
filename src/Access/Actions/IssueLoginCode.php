<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\IssuedLoginCode;
use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Access\Events\LoginCodeIssued;
use Made\CmsPackage\Access\Models\LoginCode;

final class IssueLoginCode
{
    use AsAction;

    public function handle(LoginCodeRequest $request): IssuedLoginCode
    {
        $length = config()->integer('made-cms-package.login_code.length');
        $ttl = config()->integer('made-cms-package.login_code.ttl_minutes');
        $email = mb_strtolower(trim($request->email));

        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $linkToken = Str::random(64);

        // Only the newest code of an address in this browser works, so an older mail can never be used after a new
        // request. The codes of other browsers stay: someone typing the address elsewhere cannot lock the editor out.
        LoginCode::query()->forEmail($email)->active()->boundTo($request->binding)->update(['consumed_at' => now()]);

        $loginCode = LoginCode::query()->create([
            'email' => $email,
            'code_hash' => $this->codeHash($code),
            'session_hash' => hash('sha256', $request->binding),
            'link_hash' => hash('sha256', $linkToken),
            'return_to' => $request->returnTo,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $issued = new IssuedLoginCode($loginCode, $code, $linkToken, $request->userAgent);

        LoginCodeIssued::dispatch($issued);

        return $issued;
    }

    /**
     * A keyed hash: quick and the same time for every code, while a code is only worth anything for a few minutes
     * and a few attempts. Without the app key a stored hash cannot be traced back to its code.
     */
    public function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, config()->string('app.key'));
    }
}
