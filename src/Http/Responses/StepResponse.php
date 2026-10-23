<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
