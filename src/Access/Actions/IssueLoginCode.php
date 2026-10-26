<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Support\Facades\Hash;
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

        // Only the newest code of an address works, so an older mail can never be used after a new request.
        LoginCode::query()->forEmail($email)->active()->update(['consumed_at' => now()]);

        $loginCode = LoginCode::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'session_hash' => hash('sha256', $request->binding),
            'link_hash' => hash('sha256', $linkToken),
            'return_to' => $request->returnTo,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $issued = new IssuedLoginCode($loginCode, $code, $linkToken, $request->userAgent);

        LoginCodeIssued::dispatch($issued);

        return $issued;
    }
}
