<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher new()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher add($query, $columns = null, string $orderByColumn = null)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher addFullText($query, $columns = null, array $options = [], string $orderByColumn = null)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher addMany(array $queries)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher orderBy(string $orderByColumn)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher orderByAsc()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher orderByDesc()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher orderByRelevance()
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher orderByModel($modelClasses)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher beginWithWildcard(bool $state = true)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher endWithWildcard(bool $state = true)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher ignoreCase(bool $state = true)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher soundsLike(bool $state = true)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher parseTerm(bool $state = true)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher includeModelType(string $key = 'type')
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher paginate(int $perPage = 15, string $pageName = 'page', int $page = null)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher simplePaginate(int $perPage = 15, string $pageName = 'page', int $page = null)
 * @method static \ProtoneMedia\LaravelCrossEloquentSearch\Searcher when($value, callable $callback = null, callable $default = null)
 * @method static \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator search(string $terms = null)
 * @method static int count(string $terms = null)
 * @method static \Illuminate\Support\Collection parseTerms(string $terms, callable $callback = null)
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
