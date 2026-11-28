<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListGoogleRobotsRules;

it('keeps the rules of robots.txt Googlebot follows: for every robot and for Googlebot', function (): void {
    config(['made-cms-package.sitemap.robots' => [
        '*' => ['/api/'],
        'googlebot' => ['/zoeken$'],
        'Bingbot' => ['/teams'],
    ]]);

    $rules = ListGoogleRobotsRules::run();

    expect(array_map(fn ($rule): string => $rule->userAgent.' '.$rule->path, $rules->rules))->toBe(['* /api/', 'googlebot /zoeken$'])
        ->and($rules->blocks('/api/leden'))->toBeTrue()
        ->and($rules->blocks('/zoeken'))->toBeTrue()
        ->and($rules->blocks('/zoeken/teams'))->toBeFalse()
        ->and($rules->blocks('/teams'))->toBeFalse();
});

it('blocks nothing without rules', function (): void {
    config(['made-cms-package.sitemap.robots' => []]);

    expect(ListGoogleRobotsRules::run()->blocks('/'))->toBeFalse();
});
