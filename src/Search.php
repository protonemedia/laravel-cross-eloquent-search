<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Search
{
    private Collection $pendingQueries;
    private int $perPage     = 15;
    private string $pageName = 'page';
    private $page;
    private string $orderByDirection;
    private bool $wildcardLeft = false;
    private Collection $terms;

    public function __construct()
    {
        $this->pendingQueries = new Collection;

        $this->orderByAsc();
    }

    public static function new(): self
    {
        return new static;
    }

    public function orderByAsc(): self
    {
        $this->orderByDirection = 'asc';

        return $this;
    }

    public function orderByDesc(): self
    {
        $this->orderByDirection = 'desc';

        return $this;
    }

    public function add($query, $columns, string $orderByColumn = 'updated_at'): self
    {
        $pendingQuery = new PendingQuery(
            is_string($query) ? $query::query() : $query,
            Collection::wrap($columns),
            $orderByColumn,
            $this->pendingQueries->count()
        );

        $this->pendingQueries->push($pendingQuery);

        return $this;
    }

    public function wildcardLeft(): self
    {
        $this->wildcardLeft = true;

        return $this;
    }

    private function makeOrderBy(): string
    {
        $modelOrderKeys = $this->pendingQueries->map->getModelKey('order')->implode(',');

        return "COALESCE({$modelOrderKeys})";
    }

    private function makeSelects(PendingQuery $currentPendingQuery): array
    {
        return $this->pendingQueries->flatMap(function (PendingQuery $pendingQuery) use ($currentPendingQuery) {
            $qualifiedKeyName = $qualifiedOrderByColumnName = 'null';

            if ($pendingQuery === $currentPendingQuery) {
                $qualifiedKeyName = $pendingQuery->getQualifiedKeyName();
                $qualifiedOrderByColumnName = $pendingQuery->getQualifiedOrderByColumnName();
            }

            return [
                DB::raw("{$qualifiedKeyName} as {$pendingQuery->getModelKey()}"),
                DB::raw("{$qualifiedOrderByColumnName} as {$pendingQuery->getModelKey('order')}"),
            ];
        })->all();
    }

    public function paginate($perPage = 15, $pageName = 'page', $page = null): self
    {
        $this->page     = $page ?: Paginator::resolveCurrentPage($pageName);
        $this->pageName = $pageName;
        $this->perPage  = $perPage;

        return $this;
    }

    public function addSearchQueryToBuilder(Builder $builder, PendingQuery $pendingQuery, string $term)
    {
        return $builder->where(function ($query) use ($pendingQuery) {
            $pendingQuery->getQualifiedColumns()->each(
                fn ($field)    => $this->terms->each(
                    fn ($term) => $query->orWhere($field, 'like', ($this->wildcardLeft ? '%' : '') . "{$term}%")
                )
            );
        });
    }

    private function buildQueries($term): Collection
    {
        return $this->pendingQueries->map(function (PendingQuery $pendingQuery) use ($term) {
            return $pendingQuery->getFreshBuilder()
                ->select($this->makeSelects($pendingQuery))
                ->from(DB::raw($pendingQuery->getTableAlias()))
                ->tap(function ($builder) use ($pendingQuery, $term) {
                    $this->addSearchQueryToBuilder($builder, $pendingQuery, $term);
                });
        });
    }

    public function get($term)
    {
        $this->terms = Collection::make(str_getcsv($term, ' ', '"'))->filter();

        if ($this->terms->isEmpty()) {
            throw new EmptySearchQueryException;
        }

        $queries = $this->buildQueries($term);

        $firstQuery = $queries->shift();

        $queries->each(fn (Builder $query) => $firstQuery->union($query));
        $firstQuery->orderBy(DB::raw($this->makeOrderBy()), $this->orderByDirection);

        $results = $this->perPage
            ? $firstQuery->paginate($this->perPage, ['*'], $this->pageName, $this->page)
            : $firstQuery->get();

        $modelsPerType = $this->pendingQueries
            ->keyBy->getModelKey()
            ->map(function (PendingQuery $pendingQuery, $key) use ($results) {
                $ids = $results->pluck($key)->filter();

                return $ids->isNotEmpty()
                    ? $pendingQuery->newQueryWithoutScopes()->whereKey($ids)->get()->keyBy->getKey()
                    : null;
            });

        return $results->map(function ($item) use ($modelsPerType) {
            $modelKey = Arr::first(array_flip(array_filter($item->toArray())));

            return $modelsPerType->get($modelKey)->get($item->$modelKey);
        })
            ->pipe(fn (Collection $models) => new EloquentCollection($models))
            ->when($this->perPage, fn (EloquentCollection $models) => $results->setCollection($models));
    }
}
