<?php

declare(strict_types=1);

namespace App\Support;

/** Dates stored in MySQL DATETIME columns (using PHP's configured timezone). */
final class Date
{
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
