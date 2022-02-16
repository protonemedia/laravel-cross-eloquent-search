<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher new()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher orderByAsc()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher orderByDesc()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher dontParseTerm()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher includeModelType()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher beginWithWildcard(bool $state)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher endWithWildcard(bool $state)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher soundsLike(bool $state)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher add($query, $columns, string $orderByColumn = null)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher when($value, callable $callback = null, callable $default = null)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher addMany($queries)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher paginate($perPage = 15, $pageName = 'page', $page = null)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher simplePaginate($perPage = 15, $pageName = 'page', $page = null)
 * @method static \Illuminate\Support\Collection parseTerms(string $terms, callable $callback = null)
 * @method static \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator get(string $terms = null)
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
