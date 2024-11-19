<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Exceptions;

use Exception;

class LimitAlreadyPassedException extends Exception
{
    public static function make(): static
    {
        return new static("You can't paginate if you set a limit.");
    }
}
