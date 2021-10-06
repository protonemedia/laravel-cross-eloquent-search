<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ModelToSearchThrough
{
    /**
     * Builder to search through.
     */
    protected Builder $builder;

    /**
     * The columns to search through.
     */
    protected Collection $columns;

    /**
     * Order column.
     */
    protected string $orderByColumn;

    /**
     * Unique key of this instance.
     */
    protected int $key;

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Support\Collection $columns
     * @param string $orderByColumn
     * @param integer $key
     */
    public function __construct(Builder $builder, Collection $columns, string $orderByColumn, int $key)
    {
        $this->builder       = $builder;
        $this->columns       = $columns;
        $this->orderByColumn = $orderByColumn;
        $this->key           = $key;
    }

    /**
     * Setter for the orderBy column.
     *
     * @param string $orderByColumn
     * @return self
     */
    public function orderByColumn(string $orderByColumn): self
    {
        $this->orderByColumn = $orderByColumn;

        return $this;
    }

    /**
     * Get a cloned instance of the builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getFreshBuilder(): Builder
    {
        return clone $this->builder;
    }

    /**
     * Get a collection with all columns or relations to search through.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getColumns(): Collection
    {
        return $this->columns;
    }

    /**
     * Get a collection with all qualified columns
     * to search through.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getQualifiedColumns(): Collection
    {
        return $this->columns->map(fn ($column) => $this->qualifyColumn($column));
    }

    /**
     * Get the model instance being queried.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getModel(): Model
    {
        return $this->builder->getModel();
    }

    /**
     * Generates a key for the model with a suffix.
     *
     * @param string $suffix
     * @return string
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
     *
     * @param string $column
     * @return string
     */
    public function qualifyColumn(string $column): string
    {
        return $this->getModel()->qualifyColumn($column);
    }

    /**
     * Get the qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName(): string
    {
        return $this->qualifyColumn($this->getModel()->getKeyName());
    }

    /**
     * Get the qualified order name.
     *
     * @return string
     */
    public function getQualifiedOrderByColumnName(): string
    {
        return $this->qualifyColumn($this->orderByColumn);
    }
}
