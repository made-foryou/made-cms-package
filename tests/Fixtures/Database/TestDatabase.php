<?php

declare(strict_types=1);

namespace Made\CmsPackage\Tests\Fixtures\Database;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

/**
 * The database the tests run on: SQLite in memory, or a database of their own on the MySQL or PostgreSQL server that
 * DB_CONNECTION names, reached with DB_HOST, DB_PORT, DB_USERNAME and DB_PASSWORD. The tests create that database and
 * wipe it at will, so its name is fixed here and never taken from DB_DATABASE or DB_URL: the database of the developer
 * stays untouched. Every parallel worker gets one of its own, and the next run uses it again.
 */
final class TestDatabase
{
    public const string NAME = 'made_cms_package_test';

    private const array SERVER_DRIVERS = ['mysql', 'mariadb', 'pgsql'];

    private static bool $created = false;

    /**
     * The configuration of the connection the tests use.
     *
     * @return array<string, mixed>
     */
    public static function connection(Repository $config): array
    {
        $driver = self::driver();

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        /** @var array<string, mixed> $server */
        $server = $config->array("database.connections.{$driver}");

        return [
            ...$server,
            'url' => null,
            'database' => self::name(),
            // MySQL converts a timestamp by the time zone of the session, which follows summer time on a laptop.
            ...($driver === 'pgsql' ? [] : ['timezone' => '+00:00']),
        ];
    }

    /**
     * The name of the database of this process: a worker of `pest --parallel` adds its token.
     */
    public static function name(): string
    {
        $token = getenv('TEST_TOKEN');

        return is_string($token) && $token !== '' ? self::NAME.'_'.$token : self::NAME;
    }

    /**
     * Creates the database on the server when it is not there yet, once per process. SQLite in memory needs nothing.
     */
    public static function create(DatabaseManager $databases, Repository $config): void
    {
        $driver = self::driver();

        if (self::$created || $driver === 'sqlite') {
            return;
        }

        // A database has to be created from a connection without it: MySQL connects to none, PostgreSQL to the
        // maintenance database every server has.
        /** @var Connection $server */
        $server = $databases->build([
            ...self::connection($config),
            'database' => $driver === 'pgsql' ? 'postgres' : '',
        ]);

        $exists = $driver === 'pgsql'
            ? $server->selectOne('select datname from pg_database where datname = ?', [self::name()])
            : $server->selectOne('select schema_name from information_schema.schemata where schema_name = ?', [self::name()]);

        if ($exists === null) {
            $server->getSchemaBuilder()->createDatabase(self::name());
        }

        $databases->purge($server->getName());

        self::$created = true;
    }

    private static function driver(): string
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'sqlite' || in_array($driver, self::SERVER_DRIVERS, true)) {
            return $driver;
        }

        throw new InvalidArgumentException(sprintf(
            'The tests run on sqlite, %s; DB_CONNECTION is "%s".',
            implode(', ', self::SERVER_DRIVERS),
            is_scalar($driver) ? (string) $driver : get_debug_type($driver),
        ));
    }
}
