<?php

declare(strict_types=1);

namespace App\Support;

use WilyCode\DotEnv\DotEnv;

final class Environment
{
    public static function load(string $root): void
    {
        $path = $root . '/.env';
        if (is_file($path)) {
            new DotEnv($path)->load();
        }
    }

    public static function get(string $name, string $default = ''): string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }

}
