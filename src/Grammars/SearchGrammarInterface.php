<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Grammars;

use Illuminate\Contracts\Database\Query\Expression;

interface SearchGrammarInterface
{
    public function wrap(string|Expression $value): string;

    public function caseInsensitive(string $column): string;

    /**
     * @param  array<int, string>  $values
     */
    public function coalesce(array $values): string;

    public function charLength(string $column): string;

    public function replace(string $column, string $search, string $replace): string;

    public function lower(string $column): string;

    public function soundsLikeOperator(): string;

    public function supportsSoundsLike(): bool;

    public function supportsUnionOrdering(): bool;

    /**
     * @param  array<int, mixed>  $bindings
     * @return array<string, mixed>
     */
    public function wrapUnionQuery(string $sql, array $bindings): array;
}
