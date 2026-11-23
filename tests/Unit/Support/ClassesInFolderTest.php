<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Support\ClassesInFolder;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;

const CLASSES_FIXTURES = __DIR__.'/../../Fixtures/Strips';

it('finds the concrete classes of a parent in a folder and its sub-folders', function (): void {
    expect(ClassesInFolder::find(CLASSES_FIXTURES.'/Valid', 'Made\CmsPackage\Tests\Fixtures\Strips\Valid', StripType::class))
        ->toBe([Hero::class, Cards::class]);
});

it('accepts a namespace with surrounding backslashes and a path with a trailing slash', function (): void {
    expect(ClassesInFolder::find(CLASSES_FIXTURES.'/Valid/', '\Made\CmsPackage\Tests\Fixtures\Strips\Valid\\', StripType::class))
        ->toBe([Hero::class, Cards::class]);
});

it('finds nothing in a folder that does not exist', function (): void {
    expect(ClassesInFolder::find(CLASSES_FIXTURES.'/Missing', 'App\Cms\Strips', StripType::class))->toBe([]);
});

it('skips files whose class does not match the namespace', function (): void {
    expect(ClassesInFolder::find(CLASSES_FIXTURES.'/Valid', 'App\Cms\Strips', StripType::class))->toBe([]);
});
