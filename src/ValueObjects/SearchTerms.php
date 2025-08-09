<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchTerms
{
    /**
     * @param Collection<int, string> $original
     * @param Collection<int, string> $withoutWildcards
     * @param Collection<int, string> $withWildcards
     */
    public function __construct(
        private readonly Collection $original,
        private readonly Collection $withoutWildcards,
        private readonly Collection $withWildcards,
        private readonly ?string $rawInput = null
    ) {}

    public static function parse(
        string $input,
        bool $parseTerm = true,
        bool $ignoreCase = false,
        bool $beginWithWildcard = false,
        bool $endWithWildcard = true,
        bool $soundsLike = false
    ): self {
        $original = $parseTerm ? static::parseInput($input) : collect([$input]);
        
        $withoutWildcards = $original->filter()->map(function ($term) use ($ignoreCase) {
            return $ignoreCase ? Str::lower($term) : $term;
        });

        $withWildcards = $soundsLike 
            ? $withoutWildcards 
            : $withoutWildcards->map(function ($term) use ($beginWithWildcard, $endWithWildcard) {
                return implode([
                    $beginWithWildcard ? '%' : '',
                    $term,
                    $endWithWildcard ? '%' : '',
                ]);
            });

        return new self($original, $withoutWildcards, $withWildcards, $input);
    }

    private static function parseInput(string $input): Collection
    {
        return collect(str_getcsv($input, ' ', '"'))->filter()->values();
    }

    public function original(): Collection
    {
        return $this->original;
    }

    public function withoutWildcards(): Collection
    {
        return $this->withoutWildcards;
    }

    public function withWildcards(): Collection
    {
        return $this->withWildcards;
    }

    public function rawInput(): ?string
    {
        return $this->rawInput;
    }

    public function isEmpty(): bool
    {
        return $this->withoutWildcards->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }
}