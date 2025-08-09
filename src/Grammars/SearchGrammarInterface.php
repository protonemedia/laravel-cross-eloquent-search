<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Grammars;

/**
 * Interface for database-specific search grammar implementations.
 *
 * This interface defines the contract for database-specific search functionality,
 * allowing the search package to work with different database systems while
 * providing a consistent API.
 */
interface SearchGrammarInterface
{
    /**
     * Wrap a column or table name with appropriate identifier quotes.
     *
     * @param mixed $value
     * @return string
     */
    public function wrap($value): string;
    
    /**
     * Create a case-insensitive column expression.
     *
     * @param string $column
     * @return string
     */
    public function caseInsensitive(string $column): string;
    
    /**
     * Create a COALESCE expression with the given values.
     *
     * @param array $values
     * @return string
     */
    public function coalesce(array $values): string;
    
    /**
     * Create a character length expression for the given column.
     *
     * @param string $column
     * @return string
     */
    public function charLength(string $column): string;
    
    /**
     * Create a string replace expression.
     *
     * @param string $column
     * @param string $search
     * @param string $replace
     * @return string
     */
    public function replace(string $column, string $search, string $replace): string;
    
    /**
     * Create a lowercase expression for the given column.
     *
     * @param string $column
     * @return string
     */
    public function lower(string $column): string;
    
    /**
     * Get the operator used for phonetic/sounds-like matching.
     *
     * @return string
     */
    public function soundsLikeOperator(): string;
    
    /**
     * Check if the database supports phonetic/sounds-like matching.
     *
     * @return bool
     */
    public function supportsSoundsLike(): bool;
    
    /**
     * Check if the database supports complex ordering in UNION queries.
     *
     * @return bool
     */
    public function supportsUnionOrdering(): bool;
    
    /**
     * Wrap a UNION query for databases that need special handling.
     *
     * @param string $sql
     * @param array $bindings
     * @return array
     */
    public function wrapUnionQuery(string $sql, array $bindings): array;
}