<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\RateNotFoundPath;
use Made\CmsPackage\Content\Data\NotFoundTally;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\AttentionSeverity;

it('weighs a dead door by whether Google knows it and how often people try it', function (string $path, array $hitsPerSource, AttentionSeverity $severity): void {
    expect(RateNotFoundPath::run(new NotFoundTally(PagePath::fromString($path), $hitsPerSource)))->toBe($severity);
})->with([
    'people who came from google' => ['/lid-worden-oud', ['google' => 9, 'internal' => 3, 'direct' => 2], AttentionSeverity::High],
    'one person from google' => ['/lid-worden-oud', ['google' => 1], AttentionSeverity::High],
    'googlebot fetches it' => ['/teams/merwestad-3', ['googlebot' => 2, 'direct' => 1], AttentionSeverity::High],
    'googlebot only' => ['/teams/merwestad-3', ['googlebot' => 1], AttentionSeverity::High],
    'ten people' => ['/teams/merwestad-3', ['direct' => 6, 'external' => 4], AttentionSeverity::High],
    'nine people' => ['/nieuws/2024/clubkampioenschappen', ['direct' => 5, 'internal' => 4], AttentionSeverity::Medium],
    'one person' => ['/contact.html', ['search_engine' => 1], AttentionSeverity::Medium],
    'bots only' => ['/oude-feed', ['bot' => 31], AttentionSeverity::Low],
    'bots do not make ten people' => ['/contact.html', ['direct' => 3, 'bot' => 40], AttentionSeverity::Medium],
    'nothing counted' => ['/stil', [], AttentionSeverity::Low],
    'an address bots look for' => ['/wp-admin', ['bot' => 31], AttentionSeverity::Low],
    'an address bots look for, whoever comes' => ['/wp-login.php', ['google' => 2, 'direct' => 12], AttentionSeverity::Low],
]);
