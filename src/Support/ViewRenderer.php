<?php

declare(strict_types=1);

namespace App\Support;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use WilyCode\Http\Response\HttpStatus;
use WilyCode\Http\Response\ResponseFactory;
use WilyCode\Router\Router;
use WilyCode\Template\TemplateEngine;

final readonly class ViewRenderer
{
    public function __construct(
        private Router $router,
        private TemplateEngine $templates,
        private ResponseFactory $responses,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|string[]> $headers
     */
    public function render(
        array $data = [],
        ?string $template = null,
        HttpStatus $status = HttpStatus::Ok,
        array $headers = [],
    ): ResponseInterface {
        $name = $template ?? $this->router->getCurrentRoute()?->getName();
        if ($name === null) {
            throw new LogicException('No matched route. Provide a template name explicitly.');
        }

        return $this->responses->html($this->templates->render($name, $data), $status, $headers);
    }
}
