<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Connection;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\InvalidGrammarException;
use ProtoneMedia\LaravelCrossEloquentSearch\Grammars\MySqlSearchGrammar;
use ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SQLiteSearchGrammar;
use ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface;

/**
 * Factory for creating database-specific search grammar instances.
 *
 * This factory provides database-agnostic access to search functionality by 
 * creating the appropriate grammar implementation based on the database driver.
 * Currently supports MySQL/MariaDB and SQLite.
 */
class DatabaseGrammarFactory
{
    /**
     * Create a search grammar instance for the given database connection.
     *
     * @param \Illuminate\Database\Connection $connection
     * @return \ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface
     * @throws InvalidGrammarException
     */
    public static function make(Connection $connection): SearchGrammarInterface
    {
        $driver = $connection->getDriverName();

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                return new MySqlSearchGrammar($connection);
            case 'sqlite':
                return new SQLiteSearchGrammar($connection);
            default:
                throw InvalidGrammarException::driverNotSupported($driver);
        }
    }
}