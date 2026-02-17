<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface DatabaseDialect
{
    public function getName(): string;

    public function addWhereTermsToQuery(Builder $query, array|string $column): void;

    public function useSoundsLike(): void;

    public function avoidSoundsLike(): void;

    public function makeCoalesce(Collection $keys): string;

    public function getCharLengthFunction(): string;

    public function supportsFullTextSearch(): bool;

    public function supportsOrderByModel(): bool;
}