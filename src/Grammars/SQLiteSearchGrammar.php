<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Grammars;

use Illuminate\Contracts\Database\Query\Expression;
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
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->grammar = new SQLiteGrammar($connection);
    }

    public function wrap(string|Expression $value): string
    {
        return $this->grammar->wrap($value);
    }

    /**
     * Create a case-insensitive column expression.
     */
    public function caseInsensitive(string $column): string
    {
        return "LOWER({$column})";
    }

    /**
     * @param  array<int, string>  $values
     */
    public function coalesce(array $values): string
    {
        // SQLite requires at least 2 arguments for COALESCE
        if (count($values) === 1) {
            return (string) $values[0];
        }

        $valueList = implode(',', $values);

        return "COALESCE({$valueList})";
    }

    /**
     * Create a character length expression for the given column.
     */
    public function charLength(string $column): string
    {
        return "LENGTH({$column})";
    }

    /**
     * Create a string replace expression.
     */
    public function replace(string $column, string $search, string $replace): string
    {
        return "REPLACE({$column}, {$search}, {$replace})";
    }

    /**
     * Create a substring expression.
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
     */
    public function lower(string $column): string
    {
        return "LOWER({$column})";
    }

    /**
     * Get the operator used for phonetic/sounds-like matching.
     */
    public function soundsLikeOperator(): string
    {
        return 'like';
    }

    /**
     * Check if the database supports phonetic/sounds-like matching.
     */
    public function supportsSoundsLike(): bool
    {
        return false;
    }

    /**
     * Check if the database supports complex ordering in UNION queries.
     */
    public function supportsUnionOrdering(): bool
    {
        return false;
    }

    /**
     * Wrap a UNION query for databases that need special handling.
     *
     * @param  array<int, mixed>  $bindings
     * @return array<string, mixed>
     */
    public function wrapUnionQuery(string $sql, array $bindings): array
    {
        return [
            'sql' => "({$sql}) as union_results",
            'bindings' => $bindings,
        ];
    }
}
