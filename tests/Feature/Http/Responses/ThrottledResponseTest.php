<?php

declare(strict_types=1);

use Made\CmsPackage\Http\Responses\ThrottledResponse;

it('says how many seconds to wait', function (int $seconds): void {
    $response = ThrottledResponse::make(['Retry-After' => $seconds]);

    expect($response->getStatusCode())->toBe(429)
        ->and($response->getData(true))->toBe([
            'message' => "Even rustig aan. Probeer het over {$seconds} seconden opnieuw.",
            'retry_after' => $seconds,
        ]);
})->with([1, 45, 90]);

it('says how many minutes to wait when the wait is long', function (int $seconds, int $minutes): void {
    $response = ThrottledResponse::make(['Retry-After' => $seconds]);

    expect($response->getData(true))->toBe([
        'message' => "Even rustig aan. Probeer het over {$minutes} minuten opnieuw.",
        'retry_after' => $seconds,
    ]);
})->with([
    'just over' => [91, 2],
    'most of an hour' => [3412, 57],
    'an hour' => [3600, 60],
]);
