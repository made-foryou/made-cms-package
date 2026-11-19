<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Tests\Fixtures\Database\TestDatabase;

it('runs on a database of its own, never the one DB_DATABASE or DB_URL names', function (): void {
    $connection = DB::connection();

    expect($connection->getName())->toBe('testing')
        ->and($connection->getConfig('url'))->toBeNull()
        ->and($connection->getDatabaseName())->toBe($connection->getDriverName() === 'sqlite' ? ':memory:' : TestDatabase::name())
        ->and(TestDatabase::name())->toStartWith(TestDatabase::NAME);
});
