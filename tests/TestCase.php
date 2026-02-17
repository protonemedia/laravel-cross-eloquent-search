<?php

namespace ProtoneMedia\LaravelCrossEloquentSearch\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PDO;
use ProtoneMedia\LaravelCrossEloquentSearch\ServiceProvider;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    public function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');

        $this->initDatabase();
    }

    protected function initDatabase($prefix = '')
    {
        $connection = env('DB_CONNECTION', 'mysql');
        
        DB::purge($connection);

        // Configure the appropriate database connection
        switch ($connection) {
            case 'sqlite':
                $this->app['config']->set('database.connections.sqlite', [
                    'driver'   => 'sqlite',
                    'database' => env('DB_DATABASE', ':memory:'),
                    'prefix'   => $prefix,
                ]);
                break;

            case 'pgsql':
                $this->app['config']->set('database.connections.pgsql', [
                    'driver'         => 'pgsql',
                    'host'           => env('DB_HOST', '127.0.0.1'),
                    'port'           => env('DB_PORT', '5432'),
                    'database'       => env('DB_DATABASE', 'search_test'),
                    'username'       => env('DB_USERNAME', 'homestead'),
                    'password'       => env('DB_PASSWORD', 'secret'),
                    'charset'        => 'utf8',
                    'prefix'         => $prefix,
                    'prefix_indexes' => true,
                    'schema'         => 'public',
                    'sslmode'        => 'prefer',
                ]);
                break;

            case 'mysql':
            default:
                $this->app['config']->set('database.connections.mysql', [
                    'driver'         => 'mysql',
                    'url'            => env('DATABASE_URL'),
                    'host'           => env('DB_HOST', '127.0.0.1'),
                    'port'           => env('DB_PORT', '3306'),
                    'database'       => env('DB_DATABASE', 'search_test'),
                    'username'       => env('DB_USERNAME', 'homestead'),
                    'password'       => env('DB_PASSWORD', 'secret'),
                    'unix_socket'    => env('DB_SOCKET', ''),
                    'charset'        => 'utf8mb4',
                    'collation'      => 'utf8mb4_unicode_ci',
                    'prefix'         => $prefix,
                    'prefix_indexes' => true,
                    'strict'         => true,
                    'engine'         => null,
                    'options'        => extension_loaded('pdo_mysql') ? array_filter([
                        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                    ]) : [],
                ]);
                break;
        }

        DB::setDefaultConnection($connection);

        $this->artisan('migrate:fresh');

        include_once __DIR__ . '/create_tables.php';

        (new \CreateTables)->up();
    }
}
