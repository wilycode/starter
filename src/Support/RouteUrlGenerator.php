<?php

declare(strict_types=1);

namespace App\Support;

use WilyCode\Router\Router;
use WilyCode\Router\UrlGenerator;

final readonly class RouteUrlGenerator
{
    private UrlGenerator $urls;

    public function __construct(Router $router, private BaseUrl $baseUrl)
    {
        $this->urls = new UrlGenerator($router);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int|string, mixed> $query
     */
    public function generate(
        string $name,
        array $params = [],
        array $query = [],
        ?string $fragment = null,
    ): string {
        return $this->baseUrl->prefix() . $this->urls->generate($name, $params, $query, $fragment);
    }

    public function html(
        string $name,
        array $params = [],
        array $query = [],
        ?string $fragment = null,
    ): string {
        return htmlspecialchars(
            $this->generate($name, $params, $query, $fragment),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }
}
