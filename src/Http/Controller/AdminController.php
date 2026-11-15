<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Middleware\BasicAuthMiddleware;
use App\Http\Pagination\PageNumber;
use App\Repository\ContactMessageRepository;
use App\Support\Redirector;
use App\Support\ViewRenderer;
use WilyCode\Http\Response\HttpStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use WilyCode\RouterAttribute\Attribute\Get;

final readonly class AdminController
{
    #[Get(path: '/admin', name: 'admin.index')]
    public function index(Redirector $redirector): ResponseInterface
    {
        return $redirector->route('admin.dashboard', status: HttpStatus::Found);
    }

    #[Get(path: '/admin/dashboard', name: 'admin.dashboard', middlewares: [BasicAuthMiddleware::class])]
    public function dashboard(
        ServerRequestInterface $request,
        ContactMessageRepository $messages,
        ViewRenderer $views,
    ): ResponseInterface {
        $page = PageNumber::fromRequest($request);
        $result = $messages->orderBy('id', 'DESC')->paginate($page, 20);

        return $views->render(['messages' => $result]);
    }
}
