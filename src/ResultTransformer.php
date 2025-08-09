<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResultTransformer
{
    public function __construct(
        private readonly Collection $models,
        private readonly ?string $includeModelTypeWithKey = null
    ) {}

    /**
     * Transform search results into models.
     */
    public function transform($results): SearchResult
    {
        $modelsPerType = $this->getModelsPerType($results);

        $transformedResults = $results->map(function ($item) use ($modelsPerType) {
            $modelKey = Collection::make($item)->search(function ($value, $key) {
                return $value && Str::endsWith($key, '_key');
            });

            /** @var Model $model */
            $model = $modelsPerType->get($modelKey)->get($item->$modelKey);

            if ($this->includeModelTypeWithKey) {
                $searchType = method_exists($model, 'searchType') 
                    ? $model->searchType() 
                    : class_basename($model);

                $model->setAttribute($this->includeModelTypeWithKey, $searchType);
            }

            return $model;
        });

        $eloquentCollection = new EloquentCollection($transformedResults);

        // If results were paginated, set the collection on the paginator
        if ($results instanceof LengthAwarePaginator) {
            $results->setCollection($eloquentCollection);
            return new SearchResult($results);
        }

        return new SearchResult($eloquentCollection);
    }

    /**
     * Get the models per type from the search results.
     */
    private function getModelsPerType($results): Collection
    {
        return $this->models
            ->keyBy->getModelKey()
            ->map(function (ModelToSearchThrough $modelToSearchThrough, $key) use ($results) {
                $ids = $results->pluck($key)->filter();

                return $ids->isNotEmpty()
                    ? $modelToSearchThrough->getFreshBuilder()->whereKey($ids)->get()->keyBy->getKey()
                    : null;
            });
    }
}