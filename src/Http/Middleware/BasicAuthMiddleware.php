<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WilyCode\Http\Response\HttpStatus;
use WilyCode\Http\Response\ResponseFactory;

final readonly class BasicAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactory $responses,
        private string $username,
        private string $password,
        private bool $localDevelopment = false,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->username === '' || $this->password === '') {
            return $this->responses->empty(HttpStatus::NotFound, ['Cache-Control' => 'no-store']);
        }

        if (!$this->hasSecureConnection($request)) {
            return $this->responses->empty(HttpStatus::Forbidden, ['Cache-Control' => 'no-store']);
        }

        if ($this->validCredentials($request)) {
            return $handler->handle($request)->withHeader('Cache-Control', 'private, no-store');
        }

        return $this->responses->empty(HttpStatus::Unauthorized, [
            'WWW-Authenticate' => 'Basic realm="Wily Starter Admin", charset="UTF-8"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function validCredentials(ServerRequestInterface $request): bool
    {
        $authorization = trim($request->getHeaderLine('Authorization'));
        if (preg_match('/^Basic ([A-Za-z0-9+\/=]+)$/iD', $authorization, $matches) !== 1) {
            return false;
        }

        $decoded = base64_decode($matches[1], true);
        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            return false;
        }

        [$username, $password] = explode(':', $decoded, 2);
        $validUsername = hash_equals($this->username, $username);
        $validPassword = hash_equals($this->password, $password);

        return $validUsername && $validPassword;
    }

    private function hasSecureConnection(ServerRequestInterface $request): bool
    {
        if ($request->getUri()->getScheme() === 'https') {
            return true;
        }

        if (!$this->localDevelopment) {
            return false;
        }

        // A private peer alone is not enough: Wily Run must bind its host port to loopback.
        $host = strtolower($request->getUri()->getHost());
        $localhost = $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || in_array($host, ['127.0.0.1', '::1'], true);
        $peer = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');

        return $localhost && (in_array($peer, ['127.0.0.1', '::1'], true) || self::privateAddress($peer));
    }

    private static function privateAddress(string $address): bool
    {
        $ip = @inet_pton($address);
        if ($ip === false) {
            return false;
        }

        if (strlen($ip) === 16) {
            return (ord($ip[0]) & 0xfe) === 0xfc; // IPv6 ULA fc00::/7
        }

        $octets = array_values(unpack('C4', $ip));
        return $octets[0] === 10
            || ($octets[0] === 172 && $octets[1] >= 16 && $octets[1] <= 31)
            || ($octets[0] === 192 && $octets[1] === 168);
    }
}
