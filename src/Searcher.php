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
    /**
     * Collection of models to search through.
     */
    private Collection $modelsToSearchThrough;

    /**
     * Order direction.
     */
    private string $orderByDirection;

    /**
     * Start the search term with a wildcard.
     */
    private bool $wildcardLeft = false;

    /**
     * Collection of search terms.
     */
    private Collection $terms;

    /**
     * The number of items to be shown per page.
     */
    private int $perPage = 15;

    /**
     * The query string variable used to store the page.
     */
    private string $pageName = 'page';

    /**
     * Current page.
     *
     * @var int|null
     */
    private $page;

    /**
     * Initialises the instanace with a fresh Collection and default sort.
     */
    public function __construct()
    {
        $this->modelsToSearchThrough = new Collection;

        $this->orderByAsc();
    }

    /**
     * Sets the ordering to ascending.
     *
     * @return self
     */
    public function orderByAsc(): self
    {
        $this->orderByDirection = 'asc';

        return $this;
    }

    /**
     * Sets the ordering to descending.
     *
     * @return self
     */
    public function orderByDesc(): self
    {
        $this->orderByDirection = 'desc';

        return $this;
    }

    /**
     * Add a model to search through.
     *
     * @param \Illuminate\Database\Eloquent\Builder|string $query
     * @param string|array|\Illuminate\Support\Collection $columns
     * @param string $orderByColumn
     * @return self
     */
    public function add($query, $columns, string $orderByColumn = 'updated_at'): self
    {
        $modelToSearchThrough = new ModelToSearchThrough(
            is_string($query) ? $query::query() : $query,
            Collection::wrap($columns),
            $orderByColumn,
            $this->modelsToSearchThrough->count()
        );

        $this->modelsToSearchThrough->push($modelToSearchThrough);

        return $this;
    }

    /**
     * Let's each search term start with a wildcard.
     *
     * @return self
     */
    public function wildcardLeft(): self
    {
        $this->wildcardLeft = true;

        return $this;
    }

    /**
     * Sets the pagination properties.
     *
     * @param integer $perPage
     * @param string $pageName
     * @param int|null $page
     * @return self
     */
    public function paginate($perPage = 15, $pageName = 'page', $page = null): self
    {
        $this->page     = $page ?: Paginator::resolveCurrentPage($pageName);
        $this->pageName = $pageName;
        $this->perPage  = $perPage;

        return $this;
    }

    /**
     * Creates a collection out of the given search term.
     *
     * @param string $term
     * @throws \ProtoneMedia\LaravelCrossEloquentSearch\EmptySearchQueryException
     * @return self
     */
    private function initializeTerm(string $term): self
    {
        $this->terms = Collection::make(str_getcsv($term, ' ', '"'))
            ->filter()
            ->map(fn ($term) => ($this->wildcardLeft ? '%' : '') . "{$term}%");

        if ($this->terms->isEmpty()) {
            throw new EmptySearchQueryException;
        }

        return $this;
    }

    /**
     * Adds a where clause to the builder, which encapsulates
     * a series 'orWhere' clauses for each column and for
     * each search term.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \ProtoneMedia\LaravelCrossEloquentSearch\ModelToSearchThrough $modelToSearchThrough
     * @return void
     */
    public function addSearchQueryToBuilder(Builder $builder, ModelToSearchThrough $modelToSearchThrough): void
    {
        $builder->where(function ($query) use ($modelToSearchThrough) {
            $modelToSearchThrough->getQualifiedColumns()->each(
                fn ($field) => $this->terms->each(fn ($term) => $query->orWhere($field, 'like', $term))
            );
        });
    }

    /**
     * Builds an array with all qualified columns for
     * both the ids and ordering.
     *
     * @param \ProtoneMedia\LaravelCrossEloquentSearch\ModelToSearchThrough $currentModel
     * @return array
     */
    private function makeSelects(ModelToSearchThrough $currentModel): array
    {
        return $this->modelsToSearchThrough->flatMap(function (ModelToSearchThrough $modelToSearchThrough) use ($currentModel) {
            $qualifiedKeyName = $qualifiedOrderByColumnName = 'null';

            if ($modelToSearchThrough === $currentModel) {
                $qualifiedKeyName = $modelToSearchThrough->getQualifiedKeyName();
                $qualifiedOrderByColumnName = $modelToSearchThrough->getQualifiedOrderByColumnName();
            }

            return [
                DB::raw("{$qualifiedKeyName} as {$modelToSearchThrough->getModelKey()}"),
                DB::raw("{$qualifiedOrderByColumnName} as {$modelToSearchThrough->getModelKey('order')}"),
            ];
        })->all();
    }

    /**
     * Implodes the qualified order keys with a comma and
     * wraps them in a COALESCE method.
     *
     * @return string
     */
    private function makeOrderBy(): string
    {
        $modelOrderKeys = $this->modelsToSearchThrough->map->getModelKey('order')->implode(',');

        return "COALESCE({$modelOrderKeys})";
    }

    /**
     * Builds the search queries for each given pending model.
     *
     * @return \Illuminate\Support\Collection
     */
    private function buildQueries(): Collection
    {
        return $this->modelsToSearchThrough->map(function (ModelToSearchThrough $modelToSearchThrough) {
            return $modelToSearchThrough->getFreshBuilder()
                ->select($this->makeSelects($modelToSearchThrough))
                ->tap(function ($builder) use ($modelToSearchThrough) {
                    $this->addSearchQueryToBuilder($builder, $modelToSearchThrough);
                });
        });
    }

    /**
     * Compiles all queries to one big one which binds everything together
     * using UNION statements.
     *
     * @return \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function getIdAndOrderAttributes()
    {
        $queries = $this->buildQueries();

        // take the first query
        $firstQuery = $queries->shift()->toBase();

        // union the other queries together
        $queries->each(fn (Builder $query) => $firstQuery->union($query));

        // sort by the given columns and direction
        $firstQuery->orderBy(DB::raw($this->makeOrderBy()), $this->orderByDirection);

        // get all results or limit the results by pagination
        return $this->perPage
            ? $firstQuery->paginate($this->perPage, ['*'], $this->pageName, $this->page)
            : $firstQuery->get();

        // the collection will be something like:
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
    }

    /**
     * Get the models per type.
     *
     * @param \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator $results
     * @return \Illuminate\Support\Collection
     */
    private function getModelsPerType($results)
    {
        return $this->modelsToSearchThrough
            ->keyBy->getModelKey()
            ->map(function (ModelToSearchThrough $modelToSearchThrough, $key) use ($results) {
                $ids = $results->pluck($key)->filter();

                return $ids->isNotEmpty()
                    ? $modelToSearchThrough->getFreshBuilder()->whereKey($ids)->get()->keyBy->getKey()
                    : null;
            });

        // the collection will be something like:
        //
        // [
        //     "0_post_key" => [
        //         1 => PostModel
        //     ],
        //     "1_video_key" => [
        //         3 => VideoModel
        //     ],
        // ]
    }

    /**
     * Initialize the search terms, execute the search query and retrieve all
     * models per type. Map the results to a Eloquent collection and set
     * the collection on the paginator (whenever used).
     *
     * @param string $term
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function get(string $term)
    {
        $this->initializeTerm($term);

        $results = $this->getIdAndOrderAttributes();

        $modelsPerType = $this->getModelsPerType($results);

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
