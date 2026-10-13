<?php

declare(strict_types=1);

$contexts = array_values(array_filter(
    array_map(fn (string $context): string => "Made\\CmsPackage\\{$context}", contexts()),
    fn (string $namespace): bool => is_dir(__DIR__.'/../../src/'.substr($namespace, strlen('Made\\CmsPackage\\'))),
));

if ($contexts !== []) {
    arch('contexts do not depend on technical layers')
        ->expect($contexts)
        ->not->toUse([
            'Made\CmsPackage\Http',
            'Made\CmsPackage\Console',
            'Made\CmsPackage\CmsPackageServiceProvider',
        ]);

    arch('contexts do not take HTTP input')
        ->expect($contexts)
        ->not->toUse([
            'Illuminate\Http\Request',
            'Illuminate\Foundation\Http\FormRequest',
        ]);

    foreach ($contexts as $context) {
        $others = array_values(array_diff($contexts, [$context]));

        if ($others !== []) {
            arch("{$context} does not depend on other contexts")
                ->expect($context)
                ->not->toUse($others);
        }
    }
}
