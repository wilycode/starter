<?php

declare(strict_types=1);

namespace App;

use App\Bootstrap\ContainerFactory;
use App\Support\BaseUrl;
use App\Support\Environment;
use App\Support\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use WilyCode\Http\Response\HttpStatus;
use WilyCode\Router\NotFoundException;
use WilyCode\Router\Router;
use WilyCode\RouterAttribute\AttributeRouteCollection;

final readonly class Application
{
    public function __construct(private string $root)
    {
        Environment::load($root);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $container = ContainerFactory::create($this->root, $request);
        $baseUrl = $container->get(BaseUrl::class);
        $logger = $container->get(LoggerInterface::class);

        $router = $container->get(Router::class);
        $router->addCollection(new AttributeRouteCollection([
            $this->root . '/src/Http/Controller',
        ]));
        $views = $container->get(ViewRenderer::class);

        try {
            $routeRequest = $request->withUri(
                $request->getUri()->withPath($baseUrl->relativePath($request->getUri()->getPath())),
            );
            $response = $router->dispatch($routeRequest);
        } catch (NotFoundException) {
            $response = $views->render(template: 'errors.not_found', status: HttpStatus::NotFound);
        } catch (Throwable $error) {
            try {
                $logger->error('Unhandled request error', ['exception' => $error]);
            } catch (Throwable $loggingError) {
                error_log('[Wily Starter] ' . $error::class);
            }
            $response = $views->render(template: 'errors.server_error', status: HttpStatus::InternalServerError);
        }

        // Disable browser caching without disabling the server-side page cache.
        if (!$response->hasHeader('Cache-Control')) {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }

        return $response->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
