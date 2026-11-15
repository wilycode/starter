<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Support\BaseUrl;
use Psr\Http\Message\ServerRequestInterface;
use WilyCode\Session\Php\NativeSession;
use WilyCode\Session\SessionInterface;

final class SessionFactory
{
    public static function configure(ServerRequestInterface $request, BaseUrl $baseUrl): void
    {
        // Reverse proxies must pass a trusted request scheme for HTTPS detection.
        ini_set('session.use_strict_mode', '1');
        // PSR-7 sets Cache-Control; prevent PHP from adding a second header.
        session_cache_limiter('');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $request->getUri()->getScheme() === 'https',
            'samesite' => 'Lax',
            'path' => $baseUrl->path('/'),
        ]);
    }

    public static function create(): SessionInterface
    {
        return new NativeSession();
    }
}
