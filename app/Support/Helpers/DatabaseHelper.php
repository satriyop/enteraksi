<?php

namespace App\Support\Helpers;

use Illuminate\Database\QueryException;

class DatabaseHelper
{
    /**
     * Check if a QueryException is a unique constraint violation.
     * Works across MySQL, PostgreSQL, SQLite, and SQL Server.
     */
    public static function isDuplicateKeyException(QueryException $e): bool
    {
        $code = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

        // MySQL: error code 1062
        if ($code === 1062) {
            return true;
        }

        // PostgreSQL: SQLSTATE 23505
        if ($sqlState === '23505') {
            return true;
        }

        // SQLite: error code 19 with "UNIQUE constraint failed"
        if ($code === 19 && str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return true;
        }

        // SQL Server: error code 2627
        if ($code === 2627) {
            return true;
        }

        // Fallback: check message for common patterns
        return str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'unique constraint')
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
