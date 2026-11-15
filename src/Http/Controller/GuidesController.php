<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Middleware\PageCacheMiddleware;
use App\Support\ViewRenderer;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use WilyCode\RouterAttribute\Attribute\Get;

final readonly class GuidesController
{
    #[Get(path: '/guides', name: 'guides.index', middlewares: [PageCacheMiddleware::class])]
    public function index(ViewRenderer $views): ResponseInterface
    {
        return $views->render([
            'generatedAt' => new DateTimeImmutable(),
        ]);
    }
}
