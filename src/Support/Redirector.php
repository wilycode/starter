<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface;
use WilyCode\Http\Response\HttpStatus;
use WilyCode\Http\Response\ResponseFactory;

final readonly class Redirector
{
    public function __construct(
        private RouteUrlGenerator $urls,
        private ResponseFactory $responses,
    ) {}

    /**
     * @param array<string, mixed> $params Route parameters (e.g. ['id' => 12]).
     * @param array<int|string, mixed> $query Query string parameters.
     */
    public function route(
        string $name,
        array $params = [],
        array $query = [],
        HttpStatus $status = HttpStatus::SeeOther,
        ?string $fragment = null,
    ): ResponseInterface {
        return $this->responses->redirect(
            $this->urls->generate($name, $params, $query, $fragment),
            $status,
        );
    }
}
