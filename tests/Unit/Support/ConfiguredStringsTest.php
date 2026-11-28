<?php

declare(strict_types=1);

use Made\CmsPackage\Support\ConfiguredStrings;

it('reads the texts of a list in config', function (): void {
    config(['made-cms-package.test.list' => [' MonitorDienst ', 'Uptime']]);

    expect(ConfiguredStrings::of('made-cms-package.test.list'))->toBe(['MonitorDienst', 'Uptime']);
});

it('leaves out what is no text or is empty', function (): void {
    config(['made-cms-package.test.list' => ['Uptime', 42, null, '', '  ', ['nested'], 'key' => 'Monitor']]);

    expect(ConfiguredStrings::of('made-cms-package.test.list'))->toBe(['Uptime', 'Monitor']);
});

it('reads nothing from a value that is no list, or from no value', function (mixed $value): void {
    config(['made-cms-package.test.list' => $value]);

    expect(ConfiguredStrings::of('made-cms-package.test.list'))->toBe([]);
})->with([
    'a text' => ['Uptime'],
    'null' => [null],
    'a number' => [42],
]);
