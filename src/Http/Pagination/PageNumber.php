<?php

declare(strict_types=1);

namespace App\Http\Pagination;

use Psr\Http\Message\ServerRequestInterface;

final class PageNumber
{
    public static function fromRequest(ServerRequestInterface $request): int
    {
        $page = filter_var(
            $request->getQueryParams()['page'] ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1_000_000]],
        );

        return is_int($page) ? $page : 1;
    }
}
