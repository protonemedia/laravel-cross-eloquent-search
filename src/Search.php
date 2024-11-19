<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Searcher new()
 * @method static Searcher orderByAsc()
 * @method static Searcher orderByDesc()
 * @method static Searcher dontParseTerm()
 * @method static Searcher includeModelType()
 * @method static Searcher beginWithWildcard(bool $state)
 * @method static Searcher endWithWildcard(bool $state)
 * @method static Searcher soundsLike(bool $state)
 * @method static Searcher offset(int $offset)
 * @method static Searcher limit(int $limit)
 * @method static Searcher when($value, callable $callback = null, callable $default = null)
 * @method static Searcher add(Builder|string $query, iterable|string|Collection $columns = null, null|string $orderByColumn = null)
 * @method static Searcher addMany(iterable $queries)
 * @method static Searcher paginate(int $perPage = 15, string $pageName = 'page', null|int $page = null)
 * @method static Searcher simplePaginate(int $perPage = 15, string $pageName = 'page', null|int $page = null)
 * @method static Collection parseTerms(string $terms, null|callable $callback = null)
 * @method static EloquentCollection|LengthAwarePaginator get(null|string $terms = null)
 *
 * @see \ProtoneMedia\LaravelCrossEloquentSearch\Searcher
 */
class Search extends Facade
{
    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-cross-eloquent-search';
    }
}
