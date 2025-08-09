<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;
use ProtoneMedia\LaravelCrossEloquentSearch\Contracts\SearcherContract;

class SearchFactory implements SearcherContract
{
    use ForwardsCalls;

    /**
     * Create a new Searcher instance.
     */
    public function new(): Searcher
    {
        return new Searcher;
    }

    /**
     * Add a model to search through.
     */
    public function add($query, $columns = null, string $orderByColumn = null): SearcherContract
    {
        return $this->new()->add($query, $columns, $orderByColumn);
    }

    /**
     * Add a full-text searchable model.
     */
    public function addFullText($query, $columns = null, array $options = [], string $orderByColumn = null): SearcherContract
    {
        return $this->new()->addFullText($query, $columns, $options, $orderByColumn);
    }

    /**
     * Add multiple models at once.
     */
    public function addMany(array $queries): SearcherContract
    {
        return $this->new()->addMany($queries);
    }

    /**
     * Set the order by column for the most recently added model.
     */
    public function orderBy(string $orderByColumn): SearcherContract
    {
        return $this->new()->orderBy($orderByColumn);
    }

    /**
     * Order results in ascending order.
     */
    public function orderByAsc(): SearcherContract
    {
        return $this->new()->orderByAsc();
    }

    /**
     * Order results in descending order.
     */
    public function orderByDesc(): SearcherContract
    {
        return $this->new()->orderByDesc();
    }

    /**
     * Order results by relevance.
     */
    public function orderByRelevance(): SearcherContract
    {
        return $this->new()->orderByRelevance();
    }

    /**
     * Order results by model type.
     */
    public function orderByModel($modelClasses): SearcherContract
    {
        return $this->new()->orderByModel($modelClasses);
    }

    /**
     * Configure wildcard behavior.
     */
    public function beginWithWildcard(bool $state = true): SearcherContract
    {
        return $this->new()->beginWithWildcard($state);
    }
    
    /**
     * Configure wildcard behavior.
     */
    public function endWithWildcard(bool $state = true): SearcherContract
    {
        return $this->new()->endWithWildcard($state);
    }

    /**
     * Enable case-insensitive searching.
     */
    public function ignoreCase(bool $state = true): SearcherContract
    {
        return $this->new()->ignoreCase($state);
    }

    /**
     * Enable sounds like searching.
     */
    public function soundsLike(bool $state = true): SearcherContract
    {
        return $this->new()->soundsLike($state);
    }

    /**
     * Configure term parsing.
     */
    public function parseTerm(bool $state = true): SearcherContract
    {
        return $this->new()->parseTerm($state);
    }

    /**
     * Configure pagination.
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', int $page = null): SearcherContract
    {
        return $this->new()->paginate($perPage, $pageName, $page);
    }

    /**
     * Configure simple pagination.
     */
    public function simplePaginate(int $perPage = 15, string $pageName = 'page', int $page = null): SearcherContract
    {
        return $this->new()->simplePaginate($perPage, $pageName, $page);
    }

    /**
     * Include model type in results.
     */
    public function includeModelType(string $key = 'type'): SearcherContract
    {
        return $this->new()->includeModelType($key);
    }

    /**
     * Perform the search.
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function search(string $terms = null)
    {
        return $this->new()->search($terms);
    }

    /**
     * Count the search results.
     */
    public function count(string $terms = null): int
    {
        return $this->new()->count($terms);
    }

    /**
     * Parse search terms.
     */
    public static function parseTerms(string $terms, callable $callback = null): Collection
    {
        return Searcher::parseTerms($terms, $callback);
    }

    /**
     * Handle dynamic method calls into a new Searcher instance.
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->new(), $method, $parameters);
    }
}
