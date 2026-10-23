<?php

declare(strict_types=1);

if (is_dir(__DIR__.'/../../src/Support')) {
    arch('support helpers are final and depend on no context or technical layer')
        ->expect('Made\CmsPackage\Support')
        ->toBeClasses()
        ->toBeFinal()
        ->not->toUse([
            ...array_map(fn (string $context): string => "Made\\CmsPackage\\{$context}", contexts()),
            'Made\CmsPackage\Http',
            'Made\CmsPackage\Console',
        ]);
}
