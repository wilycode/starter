<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Form\ValidationErrorFormatter;
use App\Support\BaseUrl;
use App\Support\Environment;
use App\Support\FlashMessage;
use App\Support\LocaleContext;
use App\Support\RouteUrlGenerator;
use App\Support\Storage;
use WilyCode\Template\Assets\AssetMode;
use WilyCode\Template\Assets\Assets;
use WilyCode\Template\Extension\TemplateAssetExtension;
use WilyCode\Template\Extension\TemplateStringFilterExtension;
use WilyCode\Template\Extension\TemplateTranslationExtension;
use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateEngine;
use WilyCode\Template\TemplateLoader;
use WilyCode\Translation\LocaleFormatter\LocaleFormatterFactory;

final class TemplateFactory
{
    public static function create(
        string $root,
        BaseUrl $baseUrl,
        LocaleContext $locale,
        FlashMessage $flash,
        RouteUrlGenerator $routeUrls,
    ): TemplateEngine {
        $production = Environment::get('APP_ENV', 'local') === 'production';

        $templates = new TemplateEngine(
            new TemplateLoader(new FileLocator(['' => $root . '/templates'])),
            Storage::directory($root) . '/cache/templates',
            autoReload: !$production,
        );

        $templates->addExtension(new TemplateStringFilterExtension());
        $formatter = (new LocaleFormatterFactory(
            $root . '/vendor/wilycode/translation-languages/src/resources/locales',
            'en-US',
        ))->forCode($locale->code === 'pl' ? 'pl-PL' : 'en-US');
        $templates->addExtension(new TemplateTranslationExtension(
            translator: $locale->translator,
            formatter: $formatter,
        ));
        $templates->addFilter('validation_error', [(new ValidationErrorFormatter($locale->translator)), 'format']);
        $templates->addExtension(new TemplateAssetExtension(new Assets(
            publicBasePath: $root . '/public',
            publicBaseUrl: $baseUrl->prefix(),
            mode: $production ? AssetMode::Production : AssetMode::Development,
        )));

        // Template functions echo unescaped output, so use an HTML-safe URL here.
        $templates->addFunction('route', [$routeUrls, 'html']);

        $templates->share('locale', $locale->code);
        $templates->share('appName', Environment::get('APP_NAME', 'Wily Starter'));
        $templates->share('basePath', $baseUrl->prefix());
        $templates->share('flash', $flash);
        $templates->share('adminEnabled', Environment::get('BASIC_AUTH_USERNAME') !== ''
            && Environment::get('BASIC_AUTH_PASSWORD') !== '');

        return $templates;
    }
}
