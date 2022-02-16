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
     * Full-text search.
     */
    protected bool $fullText;

    /**
     * Full-text search options
     */
    protected array $fullTextOptions = [];

    /**
     * Full-text through relation.
     */
    protected ?string $fullTextRelation = null;

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Support\Collection $columns
     * @param string $orderByColumn
     * @param integer $key
     * @param bool $fullText
     * @param array $fullTextOptions
     * @param string $fullTextRelation
     */
    public function __construct(Builder $builder, Collection $columns, string $orderByColumn, int $key, bool $fullText = false, array $fullTextOptions = [], string $fullTextRelation = null)
    {
        $this->builder          = $builder;
        $this->columns          = $columns;
        $this->orderByColumn    = $orderByColumn;
        $this->key              = $key;
        $this->fullText         = $fullText;
        $this->fullTextOptions  = $fullTextOptions;
        $this->fullTextRelation = $fullTextRelation;
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
     * Set a collection with all columns or relations to search through.
     *
     * @return $this
     */
    public function setColumns(Collection $columns): self
    {
        $this->columns = $columns;

        return $this;
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

    /**
     * Full-text search.
     *
     * @return boolean
     */
    public function isFullTextSearch(): bool
    {
        return $this->fullText;
    }

    /**
     * Full-text search options.
     *
     * @return array
     */
    public function getFullTextOptions(): array
    {
        return $this->fullTextOptions;
    }

    /**
     * Full-text through relation.
     *
     * @return string|null
     */
    public function getFullTextRelation(): ?string
    {
        return $this->fullTextRelation;
    }

    /**
     * Full-text through relation.
     *
     * @return $this
     */
    public function setFullTextRelation(?string $fullTextRelation = null): self
    {
        $this->fullTextRelation = $fullTextRelation;

        return $this;
    }

    /**
     * Clone the current instance.
     *
     * @return static
     */
    public function clone(): static
    {
        return new static($this->builder, $this->columns, $this->orderByColumn, $this->key, $this->fullText, $this->fullTextOptions, $this->fullTextRelation);
    }

    /**
     * Split the current instance into multiple based on relation search.
     *
     * @return \Illuminate\Support\Collection
     */
    public function toGroupedCollection(): Collection
    {
        if ($this->columns->all() === $this->columns->flatten()->all()) {
            return Collection::wrap($this);
        }

        $collection = Collection::make();

        foreach ($this->columns as $relation => $columns) {
            $collection->push(
                $this->clone()->setColumns(Collection::wrap($columns))->setFullTextRelation($relation)
            );
        }

        return $collection;
    }
}
