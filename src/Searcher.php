<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Searcher
{
    private Collection $pendingQueries;

    private string $orderByDirection;
    private bool $wildcardLeft = false;
    private Collection $terms;

    /** Pagination */
    private int $perPage     = 15;
    private string $pageName = 'page';
    private $page;

    /**
     * Initialises the instanace with a fresh Collection and default sort.
     */
    public function __construct()
    {
        $this->pendingQueries = new Collection;

        $this->orderByAsc();
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
                fn ($field) => $this->terms->each(fn ($term) => $query->orWhere($field, 'like', $term))
            );
        });
    }

    private function buildQueries($term): Collection
    {
        return $this->pendingQueries->map(function (PendingQuery $pendingQuery) use ($term) {
            return $pendingQuery->getFreshBuilder()
                ->select($this->makeSelects($pendingQuery))
                ->tap(function ($builder) use ($pendingQuery, $term) {
                    $this->addSearchQueryToBuilder($builder, $pendingQuery, $term);
                });
        });
    }

    private function initializeTerm($term): self
    {
        $this->terms = Collection::make(str_getcsv($term, ' ', '"'))
            ->filter()
            ->map(fn ($term) => ($this->wildcardLeft ? '%' : '') . "{$term}%");

        if ($this->terms->isEmpty()) {
            throw new EmptySearchQueryException;
        }

        return $this;
    }

    public function get(string $term)
    {
        // set the term and build all queries to perform the searches
        $queries = $this->initializeTerm($term)->buildQueries($term);

        // take the first query
        $firstQuery = $queries->shift()->toBase();

        // union the other queries together
        $queries->each(fn (Builder $query) => $firstQuery->union($query));

        // sort by the given columns and direction
        $firstQuery->orderBy(DB::raw($this->makeOrderBy()), $this->orderByDirection);

        // get all results or limit the results by pagination
        $results = $this->perPage
            ? $firstQuery->paginate($this->perPage, ['*'], $this->pageName, $this->page)
            : $firstQuery->get();

        // $results will be something like:
        //
        // [
        //     [
        //         "0_post_key": null
        //         "0_post_order": null
        //         "1_video_key": 3
        //         "1_video_order": "2020-07-07 19:51:08"
        //     ],
        //     [
        //         "0_post_key": 1
        //         "0_post_order": "2020-07-08 19:51:08"
        //         "1_video_key": null
        //         "1_video_order": null
        //     ]
        // ]

        // map over each query, pluck the relevant keys, and get the models using a fresh builder
        $modelsPerType = $this->pendingQueries
            ->keyBy->getModelKey()
            ->map(function (PendingQuery $pendingQuery, $key) use ($results) {
                $ids = $results->pluck($key)->filter();

                return $ids->isNotEmpty()
                    ? $pendingQuery->getFreshBuilder()->whereKey($ids)->get()->keyBy->getKey()
                    : null;
            });

        // $modelsPerType will be something like:
        //
        // [
        //     "0_post_key" => [
        //         1 => PostModel
        //     ],
        //     "1_video_key" => [
        //         3 => VideoModel
        //     ],
        // ]

        // loop over the results again and replace the object with the related model
        return $results->map(function ($item) use ($modelsPerType) {
            // from this set, pick '0_post_key'
            //
            // [
            //     "0_post_key": 1
            //     "0_post_order": "2020-07-08 19:51:08"
            //     "1_video_key": null
            //     "1_video_order": null
            // ]

            $modelKey = Collection::make($item)->search(function ($value, $key) {
                return $value && Str::endsWith($key, '_key');
            });

            return $modelsPerType->get($modelKey)->get($item->$modelKey);
        })
            ->pipe(fn (Collection $models) => new EloquentCollection($models))
            ->when($this->perPage, fn (EloquentCollection $models) => $results->setCollection($models));
    }
}
