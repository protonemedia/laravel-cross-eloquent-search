<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

readonly class ModelToSearchThrough
{
    /**
     * @param  Builder<Model>  $builder  Builder to search through
     * @param  Collection<int, string|Expression>  $columns  The columns to search through
     * @param  string  $orderByColumn  Order column
     * @param  int  $key  Unique key of this instance
     * @param  bool  $fullText  Full-text search
     * @param  array<string, mixed>  $fullTextOptions  Full-text search options
     * @param  string|null  $fullTextRelation  Full-text through relation
     */
    public function __construct(
        protected Builder $builder,
        protected Collection $columns,
        protected string $orderByColumn,
        protected int $key,
        protected bool $fullText = false,
        protected array $fullTextOptions = [],
        protected ?string $fullTextRelation = null,
    ) {}

    /**
     * Create a new instance with a different orderBy column.
     */
    public function orderByColumn(string $orderByColumn): self
    {
        return new self(
            $this->builder,
            $this->columns,
            $orderByColumn,
            $this->key,
            $this->fullText,
            $this->fullTextOptions,
            $this->fullTextRelation
        );
    }

    /**
     * Get a cloned instance of the builder.
     *
     * @return Builder<Model>
     */
    public function getFreshBuilder(): Builder
    {
        return clone $this->builder;
    }

    /**
     * Get a collection with all columns or relations to search through.
     *
     * @return Collection<int, string|Expression>
     */
    public function getColumns(): Collection
    {
        return $this->columns;
    }

    /**
     * Create a new instance with different columns to search through.
     *
     * @param  Collection<int, string|Expression>  $columns
     */
    public function setColumns(Collection $columns): self
    {
        return new self(
            $this->builder,
            $columns,
            $this->orderByColumn,
            $this->key,
            $this->fullText,
            $this->fullTextOptions,
            $this->fullTextRelation
        );
    }

    /**
     * Get a collection with all qualified columns
     * to search through.
     *
     * @return Collection<int, string|Expression>
     */
    public function getQualifiedColumns(): Collection
    {
        return $this->columns->map(fn($column): \Illuminate\Database\Query\Expression|string => $column instanceof Expression ? $column : $this->qualifyColumn($column));
    }

    /**
     * Get the model instance being queried.
     */
    public function getModel(): Model
    {
        return $this->builder->getModel();
    }

    /**
     * Generates a key for the model with a suffix.
     *
     * @param  string  $suffix
     */
    public function getModelKey($suffix = 'key'): string
    {
        return implode('_', [
            $this->key,
            Str::snake(class_basename($this->getModel())),
            $suffix,
        ]);
    }

    /**
     * Qualify a column by the model instance.
     */
    public function qualifyColumn(string $column): string
    {
        return $this->getModel()->qualifyColumn($column);
    }

    /**
     * Check if a column is a raw expression.
     */
    public function isRawExpression(mixed $column): bool
    {
        return $column instanceof Expression;
    }

    /**
     * Get the qualified key name.
     */
    public function getQualifiedKeyName(): string
    {
        return $this->qualifyColumn($this->getModel()->getKeyName());
    }

    /**
     * Get the qualified order name.
     */
    public function getQualifiedOrderByColumnName(): string
    {
        return $this->qualifyColumn($this->orderByColumn);
    }

    /**
     * Full-text search.
     */
    public function isFullTextSearch(): bool
    {
        return $this->fullText;
    }

    /**
     * Full-text search options.
     *
     * @return array<string, mixed>
     */
    public function getFullTextOptions(): array
    {
        return $this->fullTextOptions;
    }

    /**
     * Full-text through relation.
     */
    public function getFullTextRelation(): ?string
    {
        return $this->fullTextRelation;
    }

    /**
     * Create a new instance with a different full-text relation.
     */
    public function setFullTextRelation(?string $fullTextRelation = null): self
    {
        return new self(
            $this->builder,
            $this->columns,
            $this->orderByColumn,
            $this->key,
            $this->fullText,
            $this->fullTextOptions,
            $fullTextRelation
        );
    }

    /**
     * Clone the current instance.
     */
    public function clone(): self
    {
        return new self($this->builder, $this->columns, $this->orderByColumn, $this->key, $this->fullText, $this->fullTextOptions, $this->fullTextRelation);
    }

    /**
     * Split the current instance into multiple based on relation search.
     *
     * @return Collection<int, self>
     */
    public function toGroupedCollection(): Collection
    {
        if ($this->columns->all() === $this->columns->flatten()->all()) {
            /** @var Collection<int, self> $wrappedCollection */
            $wrappedCollection = Collection::wrap($this);

            return $wrappedCollection;
        }

        /** @var Collection<int, self> $collection */
        $collection = Collection::make();

        /** @var array<int|string, mixed> $columnsArray */
        $columnsArray = $this->columns->toArray();

        foreach ($columnsArray as $relation => $columns) {
            /** @var Collection<int, string> $wrappedColumns */
            $wrappedColumns = Collection::wrap($columns);
            $collection->push(
                $this->clone()->setColumns($wrappedColumns)->setFullTextRelation(is_int($relation) ? null : (string) $relation)
            );
        }

        return $collection;
    }
}
