<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects;

use Illuminate\Support\Collection;

class ModelOrder
{
    /**
     * @param array<class-string> $models
     */
    public function __construct(
        private readonly array $models = []
    ) {}

    public static function from(array|string $models): self
    {
        $models = is_array($models) ? $models : [$models];
        
        return new self($models);
    }

    public function getOrderFor(string $modelClass): int
    {
        $index = array_search($modelClass, $this->models);
        
        return $index !== false ? $index : count($this->models);
    }

    public function hasOrder(): bool
    {
        return !empty($this->models);
    }

    public function models(): array
    {
        return $this->models;
    }
}