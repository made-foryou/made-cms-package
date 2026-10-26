<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Data\LoginOptions;
use Made\CmsPackage\Access\Data\RecoveryCodes;
use Made\CmsPackage\Access\Data\TwoFactorSetup;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Support\QrCode;
use stdClass;

/**
 * The answer to every login request: the step the panel shows next, its data and a fresh csrf token.
 * The copy of each step lives in the launcher templates; this only carries data.
 */
final class StepResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(Request $request, string $step, array $data = []): JsonResponse
    {
        return new JsonResponse([
            'step' => $step,
            'data' => $data === [] ? new stdClass : $data,
            'csrf_token' => $request->session()->token(),
        ]);
    }

    /**
     * Step 2: how to log in, the same for every address unless the device knows it.
     */
    public static function method(Request $request, string $email, LoginOptions $options): JsonResponse
    {
        return self::make($request, 'method', [
            'email' => $email,
            'name' => $options->firstName,
            'preferred' => $options->preferred?->value,
            'methods' => array_map(fn (LoginMethod $method): string => $method->value, $options->methods),
        ]);
    }

    /**
     * Step 3 after asking for a code: the same for every address, whether a mail went out or not.
     */
    public static function code(Request $request, string $email): JsonResponse
    {
        return self::make($request, 'code', [
            'email' => $email,
            'minutes' => config()->integer('made-cms-package.login_code.ttl_minutes'),
        ]);
    }

    public static function twoFactorSetup(Request $request, TwoFactorSetup $setup): JsonResponse
    {
        return self::make($request, 'two_factor_setup', [
            'qr' => QrCode::svg($setup->otpauthUrl),
            'secret' => $setup->secret,
            'otpauth_url' => $setup->otpauthUrl,
        ]);
    }

    public static function recoveryCodes(Request $request, RecoveryCodes $codes): JsonResponse
    {
        return self::make($request, 'recovery_codes', [
            'recovery_codes' => $codes->codes,
        ]);
    }
}
