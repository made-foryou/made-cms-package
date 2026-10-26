<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * The answer when the launcher asks too often: a translated message and when to try again.
 */
final class ThrottledResponse
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public static function make(array $headers): JsonResponse
    {
        $seconds = max(1, is_numeric($headers['Retry-After'] ?? null) ? (int) $headers['Retry-After'] : 60);

        return new JsonResponse([
            'message' => __('made-cms-package::launcher.errors.throttled', ['seconds' => (string) $seconds]),
            'retry_after' => $seconds,
        ], 429, $headers);
    }
}
