<?php

declare(strict_types=1);

namespace App\Support;

use WilyCode\Translation\TranslatorInterface;

final readonly class LocaleContext
{
    public function __construct(public string $code, public TranslatorInterface $translator) {}
}
