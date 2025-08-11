<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Exceptions;

use Exception;

class InvalidGrammarException extends Exception
{
    public static function driverNotSupported(string $driver): self
    {
        return new self("Database driver '{$driver}' is not supported for cross-eloquent search.");
    }
}
