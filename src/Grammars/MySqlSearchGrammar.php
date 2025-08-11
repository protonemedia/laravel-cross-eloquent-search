<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Grammars;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;

/**
 * MySQL-specific search grammar implementation.
 */
class MySqlSearchGrammar implements SearchGrammarInterface
{
    protected MySqlGrammar $grammar;

    /**
     * Create a new MySQL search grammar instance.
     */
    public function __construct(protected Connection $connection)
    {
        $this->grammar = new MySqlGrammar($this->connection);
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
        return 'sounds like';
    }

    /**
     * Check if the database supports phonetic/sounds-like matching.
     */
    public function supportsSoundsLike(): bool
    {
        return true;
    }

    /**
     * Check if the database supports complex ordering in UNION queries.
     */
    public function supportsUnionOrdering(): bool
    {
        return true;
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return array<string, mixed>
     */
    public function wrapUnionQuery(string $sql, array $bindings): array
    {
        return ['sql' => $sql, 'bindings' => $bindings];
    }
}
