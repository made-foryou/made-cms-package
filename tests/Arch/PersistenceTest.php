<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

$models = contextNamespaces('Models');
$builders = contextNamespaces('Builders');

arch('only context Models extend the Eloquent model')
    ->expect('Made\CmsPackage')
    ->classes()
    ->not->toExtend(Model::class)
    ->ignoring($models);

if ($models !== []) {
    arch('models extend the Eloquent model')
        ->expect($models)
        ->toBeClasses()
        ->toExtend(Model::class);

    arch('models only represent a row and hold no business logic')
        ->expect($models)
        ->not->toUse([
            ...contextNamespaces('Actions'),
            ...contextNamespaces('Events'),
            'Illuminate\Support\Facades',
        ]);

    it('declares its own builder and factory on every model', function (): void {
        foreach (contextClasses('Models') as $model) {
            $reflection = new ReflectionClass($model);

            expect($reflection->getMethod('newEloquentBuilder')->getDeclaringClass()->getName())->toBe($model)
                ->and($reflection->getMethod('newFactory')->getDeclaringClass()->getName())->toBe($model);
        }
    });
}

if ($builders !== []) {
    arch('builders are final Eloquent builders')
        ->expect($builders)
        ->toBeClasses()
        ->toBeFinal()
        ->toExtend(Builder::class)
        ->toHaveSuffix('Builder');

    $writeSide = [...contextNamespaces('Actions'), ...contextNamespaces('Events')];

    if ($writeSide !== []) {
        arch('builders only read')
            ->expect($builders)
            ->not->toUse($writeSide);
    }
}

if (is_dir(__DIR__.'/../../database/factories')) {
    arch('factories are final and live in the package factory namespace')
        ->expect('Made\CmsPackage\Database\Factories')
        ->toBeClasses()
        ->toBeFinal()
        ->toExtend(Factory::class)
        ->toHaveSuffix('Factory');
}
