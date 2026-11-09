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
     * The longest wait that is still said in seconds.
     */
    private const int LONGEST_IN_SECONDS = 90;

    /**
     * @param  array<string, mixed>  $headers
     */
    public static function make(array $headers): JsonResponse
    {
        $seconds = max(1, is_numeric($headers['Retry-After'] ?? null) ? (int) $headers['Retry-After'] : 60);

        // A limit per hour can ask to wait most of an hour, which reads better in minutes.
        $message = $seconds > self::LONGEST_IN_SECONDS
            ? __('made-cms-package::launcher.errors.throttled_minutes', ['minutes' => (string) (int) ceil($seconds / 60)])
            : __('made-cms-package::launcher.errors.throttled', ['seconds' => (string) $seconds]);

        return new JsonResponse([
            'message' => $message,
            'retry_after' => $seconds,
        ], 429, $headers);
    }
}
