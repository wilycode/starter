<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Support\LocaleContext;
use Psr\Http\Message\ServerRequestInterface;
use WilyCode\Session\SessionInterface;
use WilyCode\Translation\Language\En;
use WilyCode\Translation\Language\Pl;
use WilyCode\TranslationArray\ArrayTranslator;

final class LocaleFactory
{
    public static function create(
        string $root,
        ServerRequestInterface $request,
        SessionInterface $session,
    ): LocaleContext {
        $preferences = $session->withNamespace('preferences');
        $selected = $request->getQueryParams()['lang'] ?? null;

        if (in_array($selected, ['en', 'pl'], true)) {
            $preferences->set('locale', $selected);
        }

        $stored = $preferences->get('locale', 'en');
        $language = $stored === 'pl' ? new Pl() : new En();
        $translator = new ArrayTranslator(
            directory: $root . '/languages',
            language: $language,
            fallbackLanguage: new En(),
        );

        return new LocaleContext($language->getCode(), $translator);
    }
}
