<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Grammars;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * PostgreSQL-specific search grammar implementation.
 */
class PostgreSqlSearchGrammar implements SearchGrammarInterface
{
    protected PostgresGrammar $grammar;

    /**
     * Create a new PostgreSQL search grammar instance.
     */
    public function __construct(protected Connection $connection)
    {
        $this->grammar = new PostgresGrammar($this->connection);
    }

    public function wrap(string|Expression $value): string
    {
        // Handle JSON columns specially for PostgreSQL
        if (is_string($value) && str_contains($value, '->>')) {
            // Split the JSON column path for proper wrapping
            [$table_column, $jsonPath] = explode('->>', $value, 2);
            // For PostgreSQL, we need to cast string columns to JSON first when using JSON operators
            return "(" . $this->grammar->wrap($table_column) . ")::json->>" . $jsonPath;
        }
        
        return $this->grammar->wrap($value);
    }

    /**
     * Create a case-insensitive column expression.
     */
    public function caseInsensitive(string $column): string
    {
        // Handle JSON columns by casting to text first for PostgreSQL
        if (str_contains($column, '->>')) {
            // First cast the base column to JSON, then extract the value, then cast to text
            $parts = explode('->>', $column, 2);
            if (count($parts) === 2) {
                $baseColumn = $parts[0];
                $jsonPath = $parts[1];
                return "LOWER(({$baseColumn})::json->>{$jsonPath}::text)";
            }
        }
        
        return "LOWER({$column})";
    }

    /**
     * @param  array<int, string>  $values
     */
    public function coalesce(array $values): string
    {
        $valueList = implode(',', $values);

        return "COALESCE({$valueList})";
    }

    /**
     * Create a character length expression for the given column.
     */
    public function charLength(string $column): string
    {
        return "CHAR_LENGTH({$column})";
    }

    /**
     * Create a string replace expression.
     */
    public function replace(string $column, string $search, string $replace): string
    {
        return "REPLACE({$column}, {$search}, {$replace})";
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
        // PostgreSQL doesn't have a built-in SOUNDS LIKE operator
        // Could use extensions like fuzzystrmatch with soundex() function
        // For now, fall back to ILIKE for case-insensitive matching
        return 'ilike';
    }

    /**
     * Check if the database supports phonetic/sounds-like matching.
     */
    public function supportsSoundsLike(): bool
    {
        // PostgreSQL supports phonetic matching through extensions like fuzzystrmatch
        // However, we'll return false for the basic implementation to keep it simple
        // Users can enable the extension and customize this if needed
        return false;
    }

    /**
     * Check if the database supports complex ordering in UNION queries.
     */
    public function supportsUnionOrdering(): bool
    {
        // PostgreSQL doesn't support complex ORDER BY expressions in UNION queries
        // Similar to SQLite, we need to wrap the UNION in a subquery
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