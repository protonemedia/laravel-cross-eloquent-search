<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface SearcherContract
{
    /**
     * Add a model to search through.
     *
     * @param Builder|class-string $query
     * @param string|array|Collection|null $columns
     * @param string|null $orderByColumn
     */
    public function add($query, $columns = null, string $orderByColumn = null): self;

    /**
     * Add a full-text searchable model.
     *
     * @param Builder|class-string $query
     * @param string|array|Collection|null $columns
     * @param array $options
     * @param string|null $orderByColumn
     */
    public function addFullText($query, $columns = null, array $options = [], string $orderByColumn = null): self;

    /**
     * Add multiple models at once.
     *
     * @param array $queries
     */
    public function addMany(array $queries): self;

    /**
     * Set the order by column for the most recently added model.
     *
     * @param string $orderByColumn
     */
    public function orderBy(string $orderByColumn): self;

    /**
     * Order results in ascending order.
     */
    public function orderByAsc(): self;

    /**
     * Order results in descending order.
     */
    public function orderByDesc(): self;

    /**
     * Order results by relevance.
     */
    public function orderByRelevance(): self;

    /**
     * Order results by model type.
     *
     * @param array|string $modelClasses
     */
    public function orderByModel($modelClasses): self;

    /**
     * Configure wildcard behavior.
     */
    public function beginWithWildcard(bool $state = true): self;
    
    /**
     * Configure wildcard behavior.
     */
    public function endWithWildcard(bool $state = true): self;

    /**
     * Enable case-insensitive searching.
     */
    public function ignoreCase(bool $state = true): self;

    /**
     * Enable sounds like searching.
     */
    public function soundsLike(bool $state = true): self;

    /**
     * Configure term parsing.
     */
    public function parseTerm(bool $state = true): self;

    /**
     * Configure pagination.
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', int $page = null): self;

    /**
     * Configure simple pagination.
     */
    public function simplePaginate(int $perPage = 15, string $pageName = 'page', int $page = null): self;

    /**
     * Include model type in results.
     */
    public function includeModelType(string $key = 'type'): self;

    /**
     * Perform the search.
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function search(string $terms = null);

    /**
     * Count the search results.
     */
    public function count(string $terms = null): int;

    /**
     * Parse search terms.
     */
    public static function parseTerms(string $terms, callable $callback = null): Collection;
}