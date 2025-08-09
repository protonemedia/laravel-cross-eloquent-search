<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Exceptions;

class NoModelsAddedException extends CrossEloquentSearchException
{
    public static function make(): self
    {
        return new self('No models have been added to search through.');
    }
}