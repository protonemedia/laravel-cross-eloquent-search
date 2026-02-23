<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

trait HandlesMySQL
{
    /**
     * MySQL uses CHAR_LENGTH for string length.
     */
    protected function getMySQLStringLengthFunction(): string
    {
        return 'CHAR_LENGTH';
    }

    /**
     * MySQL uses COALESCE directly without subquery wrapping.
     */
    protected function makeMySQLOrderBy(string $modelOrderKeys): string
    {
        return "COALESCE({$modelOrderKeys}, NULL)";
    }
}
