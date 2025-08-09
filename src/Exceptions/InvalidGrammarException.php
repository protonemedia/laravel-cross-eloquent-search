<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Exceptions;

class InvalidGrammarException extends CrossEloquentSearchException
{
    public static function driverNotSupported(string $driver): self
    {
        return new self("Database driver '{$driver}' is not supported for cross-eloquent search.");
    }
}