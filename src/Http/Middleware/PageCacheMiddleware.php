<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LocaleContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use WilyCode\Http\Response\ResponseFactory;

final readonly class PageCacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheInterface $cache,
        private ResponseFactory $responses,
        private LocaleContext $locale,
        private int $ttl = 60,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->canCacheRequest($request)) {
            return $this->mark($handler->handle($request), 'BYPASS');
        }

        $uri = $request->getUri();
        $key = hash('sha256', $uri->getScheme() . '|' . $uri->getAuthority() . '|' . $uri->getPath() . '|' . $this->locale->code);

        try {
            $html = $this->cache->get($key);
            if (is_string($html)) {
                return $this->responses->html($html, headers: [
                    'Cache-Control' => 'no-store',
                    'X-Page-Cache' => 'HIT',
                ]);
            }
        } catch (CacheException|RuntimeException) {
            return $this->mark($handler->handle($request), 'BYPASS');
        }

        $response = $handler->handle($request);
        if (!$this->canCacheResponse($response)) {
            return $this->mark($response, 'BYPASS');
        }

        try {
            $saved = $this->cache->set($key, (string) $response->getBody(), $this->ttl);
        } catch (CacheException|RuntimeException) {
            $saved = false;
        }

        return $this->mark($response, $saved ? 'MISS' : 'BYPASS');
    }

    private function canCacheRequest(ServerRequestInterface $request): bool
    {
        if ($this->ttl <= 0 || $request->getMethod() !== 'GET') {
            return false;
        }
        if ($request->hasHeader('Authorization')) {
            return false;
        }
        // Unknown query parameters could personalize the response.
        if (!in_array($request->getUri()->getQuery(), ['', 'lang=pl', 'lang=en'], true)) {
            return false;
        }

        // Browser reload should still use the server cache.
        return !str_contains(strtolower($request->getHeaderLine('Cache-Control')), 'no-store');
    }

    private function canCacheResponse(ResponseInterface $response): bool
    {
        if ($response->getStatusCode() !== 200
            || !str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/html')) {
            return false;
        }

        // Avoid sharing session-specific or encoded responses.
        foreach (['Set-Cookie', 'Vary', 'Content-Encoding'] as $header) {
            if ($response->hasHeader($header)) {
                return false;
            }
        }
        $cacheControl = strtolower($response->getHeaderLine('Cache-Control'));
        foreach (['private', 'no-cache', 'no-store'] as $directive) {
            if (str_contains($cacheControl, $directive)) {
                return false;
            }
        }

        return true;
    }

    private function mark(ResponseInterface $response, string $status): ResponseInterface
    {
        return $response->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Page-Cache', $status);
    }
}
