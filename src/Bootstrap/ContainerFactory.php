<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Http\Middleware\BasicAuthMiddleware;
use App\Http\Middleware\PageCacheMiddleware;
use App\Support\BaseUrl;
use App\Support\DatabaseFactory;
use App\Support\Environment;
use App\Support\FlashMessage;
use App\Support\LocaleContext;
use App\Support\RouteUrlGenerator;
use App\Support\Storage;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use WilyCode\Container\Container;
use WilyCode\Database\Driver\DatabaseInterface;
use WilyCode\Database\DriverPdo\PdoBinder;
use WilyCode\Database\QueryExecutor\QueryExecutor;
use WilyCode\Database\QueryExecutor\QueryExecutorInterface;
use WilyCode\FormCsrf\CsrfTokenManagerInterface;
use WilyCode\FormCsrf\SessionCsrfTokenManager;
use WilyCode\FileCache\FileCache;
use WilyCode\Http\Response\ResponseFactory;
use WilyCode\Logger\FileLogger;
use WilyCode\Router\Router;
use WilyCode\Session\SessionInterface;
use WilyCode\Template\TemplateEngine;

final class ContainerFactory
{
    public static function create(string $root, ServerRequestInterface $request): Container
    {
        $baseUrl = new BaseUrl(Environment::get('APP_URL', 'http://localhost:8090'));
        $session = SessionFactory::create();
        $locale = LocaleFactory::create($root, $request, $session);
        $flash = new FlashMessage($session);

        $container = new Container();
        $router = new Router($container);
        $routeUrls = new RouteUrlGenerator($router, $baseUrl);
        $templates = TemplateFactory::create($root, $baseUrl, $locale, $flash, $routeUrls);

        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);
        $logger = new FileLogger(Storage::directory($root) . '/logs/app.log');

        $container->set(Router::class, $router);
        $container->set(RouteUrlGenerator::class, $routeUrls);
        $container->set(SessionInterface::class, $session);
        $container->set(FlashMessage::class, $flash);
        $container->set(LocaleContext::class, $locale);
        $container->set(CsrfTokenManagerInterface::class, new SessionCsrfTokenManager($session));
        $container->set(BaseUrl::class, $baseUrl);
        $container->set(TemplateEngine::class, $templates);
        $container->set(ResponseFactory::class, $responses);
        $container->set(
            BasicAuthMiddleware::class,
            static fn (ContainerInterface $c): BasicAuthMiddleware => new BasicAuthMiddleware(
                $c->get(ResponseFactory::class),
                Environment::get('BASIC_AUTH_USERNAME'),
                Environment::get('BASIC_AUTH_PASSWORD'),
                Environment::get('APP_ENV', 'local') === 'local',
            ),
        );
        $container->set(
            CacheInterface::class,
            static fn (): CacheInterface => new FileCache($root . '/var/cache/pages'),
        );
        $container->set(
            PageCacheMiddleware::class,
            static fn (ContainerInterface $c): PageCacheMiddleware => new PageCacheMiddleware(
                $c->get(CacheInterface::class),
                $c->get(ResponseFactory::class),
                $c->get(LocaleContext::class),
                self::pageCacheTtl(),
            ),
        );
        $container->set(LoggerInterface::class, $logger);
        $container->set(
            DatabaseInterface::class,
            static fn (ContainerInterface $c): DatabaseInterface => DatabaseFactory::connect(),
        );
        $container->set(
            QueryExecutorInterface::class,
            static fn (ContainerInterface $c): QueryExecutorInterface => new QueryExecutor(
                $c->get(DatabaseInterface::class),
                new PdoBinder(),
            ),
        );

        return $container;
    }

    private static function pageCacheTtl(): int
    {
        $value = Environment::get('PAGE_CACHE_TTL', '60');
        return preg_match('/^(0|[1-9][0-9]{0,3})$/D', $value) === 1
            ? min((int) $value, 3600)
            : 60;
    }
}
