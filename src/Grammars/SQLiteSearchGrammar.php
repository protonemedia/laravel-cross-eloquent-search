<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;

/**
 * SQLite-specific search grammar implementation.
 */
class SQLiteSearchGrammar implements SearchGrammarInterface
{
    protected Connection $connection;
    protected SQLiteGrammar $grammar;

    /**
     * Create a new SQLite search grammar instance.
     *
     * @param \Illuminate\Database\Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->grammar = new SQLiteGrammar($connection);
    }

    /**
     * Wrap a column or table name with appropriate identifier quotes.
     *
     * @param mixed $value
     * @return string
     */
    public function wrap($value): string
    {
        return $this->grammar->wrap($value);
    }

    /**
     * Create a case-insensitive column expression.
     *
     * @param string $column
     * @return string
     */
    public function caseInsensitive(string $column): string
    {
        return "LOWER({$column})";
    }

    /**
     * Create a COALESCE expression with the given values.
     *
     * @param array $values
     * @return string
     */
    public function coalesce(array $values): string
    {
        // SQLite requires at least 2 arguments for COALESCE
        if (count($values) === 1) {
            return $values[0];
        }
        
        $valueList = implode(',', $values);
        return "COALESCE({$valueList})";
    }

    /**
     * Create a character length expression for the given column.
     *
     * @param string $column
     * @return string
     */
    public function charLength(string $column): string
    {
        return "LENGTH({$column})";
    }

    /**
     * Create a string replace expression.
     *
     * @param string $column
     * @param string $search
     * @param string $replace
     * @return string
     */
    public function replace(string $column, string $search, string $replace): string
    {
        return "REPLACE({$column}, {$search}, {$replace})";
    }
    
    /**
     * Create a substring expression.
     *
     * @param string $column
     * @param int $start
     * @param int|null $length
     * @return string
     */
    public function substr(string $column, int $start, ?int $length = null): string
    {
        if ($length === null) {
            return "SUBSTR({$column}, {$start})";
        }
        return "SUBSTR({$column}, {$start}, {$length})";
    }

    /**
     * Create a lowercase expression for the given column.
     *
     * @param string $column
     * @return string
     */
    public function lower(string $column): string
    {
        return "LOWER({$column})";
    }

    /**
     * Get the operator used for phonetic/sounds-like matching.
     *
     * @return string
     */
    public function soundsLikeOperator(): string
    {
        return 'like';
    }

    /**
     * Check if the database supports phonetic/sounds-like matching.
     *
     * @return bool
     */
    public function supportsSoundsLike(): bool
    {
        return false;
    }

    /**
     * Check if the database supports complex ordering in UNION queries.
     *
     * @return bool
     */
    public function supportsUnionOrdering(): bool
    {
        return false;
    }

    /**
     * Wrap a UNION query for databases that need special handling.
     *
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    public function wrapUnionQuery(string $sql, array $bindings): array
    {
        return [
            'sql' => "({$sql}) as union_results",
            'bindings' => $bindings
        ];
    }
}