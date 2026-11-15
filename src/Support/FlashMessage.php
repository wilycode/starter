<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use WilyCode\Session\SessionInterface;

final readonly class FlashMessage
{
    private const KEY = 'messages';

    private SessionInterface $bag;

    public function __construct(SessionInterface $session)
    {
        $this->bag = $session->withNamespace('flash');
    }

    public function set(string $message, string $type = 'info'): void
    {
        $type = $type === 'danger' ? 'error' : $type;
        if (!in_array($type, ['success', 'error', 'warning', 'info'], true)) {
            throw new InvalidArgumentException('Unsupported flash message type: ' . $type);
        }

        $messages = $this->bag->get(self::KEY, []);
        $messages[] = ['type' => $type, 'message' => $message];
        $this->bag->set(self::KEY, $messages);
    }

    public function success(string $message): void
    {
        $this->set($message, 'success');
    }

    public function error(string $message): void
    {
        $this->set($message, 'error');
    }

    public function warning(string $message): void
    {
        $this->set($message, 'warning');
    }

    public function info(string $message): void
    {
        $this->set($message, 'info');
    }

    /** @return list<array{type: string, message: string}> */
    public function pullAll(): array
    {
        $messages = $this->bag->get(self::KEY, []);
        $this->bag->remove(self::KEY);

        return $messages;
    }
}
