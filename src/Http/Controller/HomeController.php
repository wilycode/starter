<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Support\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use WilyCode\RouterAttribute\Attribute\Get;

final readonly class HomeController
{
    #[Get(path: '/', name: 'home.index')]
    public function index(ViewRenderer $views): ResponseInterface
    {
        return $views->render();
    }
}
