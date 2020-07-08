<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PendingQuery
{
    private Builder $builder;
    private Collection $columns;
    private string $orderByColumn;
    private int $key;

    public function __construct(Builder $builder, Collection $columns, string $orderByColumn, int $key)
    {
        $this->builder       = $builder;
        $this->columns       = $columns;
        $this->orderByColumn = $orderByColumn;
        $this->key           = $key;
    }

    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    public function getFreshBuilder(): Builder
    {
        return clone $this->builder;
    }

    public function getQualifiedColumns(): Collection
    {
        return $this->columns->map(fn ($column) => $this->qualifyColumn($column));
    }

    public function getModel(): Model
    {
        return $this->getBuilder()->getModel();
    }

    public function getModelKey($suffix = 'key'): string
    {
        return implode('_', [
            $this->key,
            Str::snake(class_basename($this->getModel())),
            $suffix,
        ]);
    }

    public function getOrderByColumn(): string
    {
        return $this->orderByColumn;
    }

    public function qualifyColumn(string $column): string
    {
        return $this->getModel()->qualifyColumn($column);
    }

    public function getQualifiedKeyName(): string
    {
        return $this->qualifyColumn($this->getModel()->getKeyName());
    }

    public function getQualifiedOrderByColumnName(): string
    {
        return $this->qualifyColumn($this->getOrderByColumn());
    }
}
