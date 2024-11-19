<?php

declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch\Exceptions;

use Exception;

class OffsetAlreadyPassedException extends Exception
{
    public static function make(): static
    {
        return new static("You can't paginate if you use a offset.");
    }
}
