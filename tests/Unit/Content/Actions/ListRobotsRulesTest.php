<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListRobotsRules;
use Made\CmsPackage\Content\Data\RobotsRule;
use Made\CmsPackage\Content\Enums\RobotsRuleOrigin;

it('lists the rules the developer set in config, per user agent in their order', function (): void {
    config(['made-cms-package.sitemap.robots' => [
        '*' => ['/api/', '/cms/'],
        'Googlebot' => ['/zoeken$'],
    ]]);

    $rules = array_map(
        fn (RobotsRule $rule): array => [$rule->userAgent, $rule->path, $rule->origin],
        ListRobotsRules::run(),
    );

    expect($rules)->toBe([
        ['*', '/api/', RobotsRuleOrigin::Config],
        ['*', '/cms/', RobotsRuleOrigin::Config],
        ['Googlebot', '/zoeken$', RobotsRuleOrigin::Config],
    ]);
});

it('takes a single path for a user agent as a list of one', function (): void {
    config(['made-cms-package.sitemap.robots' => ['*' => '/api/']]);

    expect(array_map(fn (RobotsRule $rule): string => $rule->path, ListRobotsRules::run()))->toBe(['/api/']);
});

it('leaves out what it cannot write as a rule, so robots.txt never breaks', function (mixed $robots): void {
    config(['made-cms-package.sitemap.robots' => $robots]);

    expect(array_map(fn (RobotsRule $rule): string => $rule->userAgent.' '.$rule->path, ListRobotsRules::run()))->toBe(['* /api/']);
})->with([
    'spaces around' => [['  *  ' => ['  /api/  ']]],
    'an empty path' => [['*' => ['/api/', '', '   ']]],
    'no text' => [['*' => ['/api/', 12, null, ['/x']]]],
    'an empty user agent' => [['*' => ['/api/'], ' ' => ['/x']]],
    'a list without user agents' => [['*' => ['/api/'], 0 => ['/x']]],
    'a line break' => [['*' => ['/api/', "/x\nDisallow: /"], "Bad\nbot" => ['/y']]],
]);

it('has no rules without config', function (mixed $robots): void {
    config(['made-cms-package.sitemap.robots' => $robots]);

    expect(ListRobotsRules::run())->toBe([]);
})->with(['nothing' => [null], 'an empty list' => [[]], 'a text' => ['/api/']]);

it('knows which rules keep Google away', function (string $userAgent, bool $concernsGoogle): void {
    expect((new RobotsRule($userAgent, '/zoeken', RobotsRuleOrigin::Config))->concernsGoogle())->toBe($concernsGoogle);
})->with([
    'every robot' => ['*', true],
    'Googlebot' => ['Googlebot', true],
    'googlebot in other capitals' => ['googlebot', true],
    'another robot' => ['GPTBot', false],
    'Googlebot-Image' => ['Googlebot-Image', false],
]);

it('reads its path as Google does', function (): void {
    $rule = new RobotsRule('*', '/*.pdf$', RobotsRuleOrigin::Config);

    expect($rule->matches('/downloads/reglement.pdf'))->toBeTrue()
        ->and($rule->matches('/downloads/reglement.pdf?versie=2'))->toBeFalse();
});
