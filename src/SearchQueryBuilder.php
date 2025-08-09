<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ProtoneMedia\LaravelCrossEloquentSearch\Exceptions\OrderByRelevanceException;
use ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface;
use ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects\SearchTerms;
use ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects\SearchOperator;

class SearchQueryBuilder
{
    public function __construct(
        private readonly SearchGrammarInterface $grammar,
        private readonly SearchConfiguration $config
    ) {}

    /**
     * Add search constraints to the builder.
     */
    public function addSearchQuery(Builder $builder, ModelToSearchThrough $model, SearchTerms $terms): void
    {
        if ($terms->isEmpty()) {
            return;
        }

        $builder->where(function (Builder $query) use ($model, $terms) {
            if (!$model->isFullTextSearch()) {
                return $model->getColumns()->each(function ($column) use ($query, $model, $terms) {
                    Str::contains($column, '.')
                        ? $this->addNestedRelationQuery($query, $column, $terms)
                        : $this->addWhereTermsQuery($query, $model->qualifyColumn($column), $terms);
                });
            }

            $this->addFullTextQuery($query, $model, $terms);
        });
    }

    /**
     * Add relevance scoring to the builder.
     */
    public function addRelevanceQuery(Builder $builder, ModelToSearchThrough $model, SearchTerms $terms): void
    {
        if (!$this->config->isOrderingByRelevance() || $terms->isEmpty()) {
            return;
        }

        if (Str::contains($model->getColumns()->implode(''), '.')) {
            throw OrderByRelevanceException::relationColumnsNotSupported();
        }

        $expressionsAndBindings = $model->getQualifiedColumns()->flatMap(function ($field) use ($model, $terms) {
            $connection = $model->getModel()->getConnection();
            $prefix = $connection->getTablePrefix();
            $field = $this->grammar->wrap($prefix . $field);

            return $terms->withoutWildcards()->map(function ($term) use ($field) {
                $lowerField = $this->grammar->lower($field);
                $charLength = $this->grammar->charLength($lowerField);
                $replace = $this->grammar->replace($lowerField, '?', '?');
                $replacedCharLength = $this->grammar->charLength($replace);

                return [
                    'expression' => $this->grammar->coalesce(["{$charLength} - {$replacedCharLength}", '0']),
                    'bindings'   => [Str::lower($term), Str::substr(Str::lower($term), 1)],
                ];
            });
        });

        $selects  = $expressionsAndBindings->map->expression->implode(' + ');
        $bindings = $expressionsAndBindings->flatMap->bindings->all();

        $builder->selectRaw("{$selects} as terms_count", $bindings);
    }

    /**
     * Add nested relation search query.
     */
    private function addNestedRelationQuery(Builder $query, string $nestedRelationAndColumn, SearchTerms $terms): void
    {
        $segments = explode('.', $nestedRelationAndColumn);
        $column = array_pop($segments);
        $relation = implode('.', $segments);

        $query->orWhereHas($relation, function ($relationQuery) use ($column, $terms) {
            $relationQuery->where(
                fn ($query) => $this->addWhereTermsQuery($query, $query->qualifyColumn($column), $terms)
            );
        });
    }

    /**
     * Add where terms query for a column.
     */
    private function addWhereTermsQuery(Builder $query, string $column, SearchTerms $terms): void
    {
        $operator = $this->determineOperator();
        $column = $this->config->ignoreCase() ? $this->grammar->wrap($column) : $column;

        $terms->withWildcards()->each(function ($term) use ($query, $column, $operator) {
            $this->config->ignoreCase()
                ? $query->orWhereRaw($this->grammar->caseInsensitive($column) . " {$operator->value} ?", [$term])
                : $query->orWhere($column, $operator->value, $term);
        });
    }

    /**
     * Add full-text search query.
     */
    private function addFullTextQuery(Builder $query, ModelToSearchThrough $model, SearchTerms $terms): void
    {
        $model->toGroupedCollection()->each(function (ModelToSearchThrough $modelToSearchThrough) use ($query, $terms) {
            if ($relation = $modelToSearchThrough->getFullTextRelation()) {
                $query->orWhereHas($relation, function ($relationQuery) use ($modelToSearchThrough, $terms) {
                    $relationQuery->where(function ($query) use ($modelToSearchThrough, $terms) {
                        $query->orWhereFullText(
                            $modelToSearchThrough->getColumns()->all(),
                            $terms->rawInput(),
                            $modelToSearchThrough->getFullTextOptions()
                        );
                    });
                });
            } else {
                $query->orWhereFullText(
                    $modelToSearchThrough->getColumns()->map(fn ($column) => $modelToSearchThrough->qualifyColumn($column))->all(),
                    $terms->rawInput(),
                    $modelToSearchThrough->getFullTextOptions()
                );
            }
        });
    }

    /**
     * Determine the search operator to use.
     */
    private function determineOperator(): SearchOperator
    {
        if ($this->config->soundsLike() && $this->grammar->supportsSoundsLike()) {
            return SearchOperator::SOUNDS_LIKE;
        }

        return SearchOperator::LIKE;
    }
}