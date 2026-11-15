<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final readonly class BaseUrl
{
    private string $prefix;

    public function __construct(string $appUrl)
    {
        $path = parse_url($appUrl, PHP_URL_PATH);
        if ($path === false) {
            throw new InvalidArgumentException('APP_URL must contain a valid URL.');
        }

        $path = is_string($path) ? $path : '';
        $this->prefix = rtrim($path, '/');
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function path(string $path = '/'): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('Application URL path must start with "/".');
        }

        return $this->prefix . $path;
    }

    public function relativePath(string $requestPath): string
    {
        if ($this->prefix === '') {
            return $requestPath;
        }

        if ($requestPath === $this->prefix || $requestPath === $this->prefix . '/') {
            return '/';
        }

        if (str_starts_with($requestPath, $this->prefix . '/')) {
            return substr($requestPath, strlen($this->prefix));
        }

        return $requestPath;
    }
}
