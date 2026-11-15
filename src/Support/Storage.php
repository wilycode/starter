<?php

declare(strict_types=1);

namespace App\Support;

final class Storage
{
    public static function directory(string $root): string
    {
        $project = rtrim($root, '/\\') . '/var';

        if (self::ensureWritableDirectories($project)) {
            return $project;
        }

        $fallback = rtrim(sys_get_temp_dir(), '/\\')
            . '/wily-starter-'
            . substr(hash('sha256', $root), 0, 12);

        if (self::ensureWritableDirectories($fallback)) {
            return $fallback;
        }

        throw new \RuntimeException(
            'Unable to create writable cache and log directories in the project or system temp directory.',
        );
    }

    private static function ensureWritableDirectories(string $base): bool
    {
        foreach ([$base . '/cache/templates', $base . '/logs'] as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                return false;
            }

            if (!is_writable($directory)) {
                return false;
            }
        }

        return true;
    }
}
