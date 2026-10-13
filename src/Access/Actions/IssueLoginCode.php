<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\IssuedLoginCode;
use Made\CmsPackage\Access\Events\LoginCodeIssued;
use Made\CmsPackage\Access\Models\LoginCode;

final class IssueLoginCode
{
    use AsAction;

    public function handle(string $email): IssuedLoginCode
    {
        $length = config()->integer('made-cms-package.login_code.length');
        $ttl = config()->integer('made-cms-package.login_code.ttl_minutes');

        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $loginCode = LoginCode::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $issued = new IssuedLoginCode($loginCode, $code);

        LoginCodeIssued::dispatch($issued);

        return $issued;
    }
}
