<!doctype html>
<html lang="{{ $locale }}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <link id="theme-favicon" rel="icon" type="image/svg+xml" href="{{ $basePath }}/assets/images/wilycode-symbol-light.svg"
              data-light-icon="{{ $basePath }}/assets/images/wilycode-symbol-light.svg"
              data-dark-icon="{{ $basePath }}/assets/images/wilycode-symbol-dark.svg">
        <script src="{{ $basePath }}/assets/js/theme-init.js"></script>
        <title>{{ $appName }}</title>
        @css('/assets/css/app.css')
        @renderCss()
    </head>
    <body>
        <div class="site-shell">
            <header class="site-header">
                <a class="brand" href="@route('home.index')" aria-label="{{ 'nav.brand_label' | gtrans }}">
                    <span class="brand-mark">
                        <img class="brand-logo-light" src="{{ $basePath }}/assets/images/wilycode-symbol-light.svg" alt="" width="40" height="40">
                        <img class="brand-logo-dark" src="{{ $basePath }}/assets/images/wilycode-symbol-dark.svg" alt="" width="40" height="40">
                    </span>
                    <span>Wily <span class="brand-light">Starter</span></span>
                </a>
                <nav class="navigation" aria-label="{{ 'nav.label' | gtrans }}">
                    <a href="@route('home.index')">{{ 'nav.home' | gtrans }}</a>
                    <a href="@route('contact.index')">{{ 'nav.contact' | gtrans }}</a>
                    <a href="@route('guides.index')">{{ 'nav.guides' | gtrans }}</a>
                    @if($adminEnabled)
                        <a href="@route('admin.dashboard')">{{ 'nav.admin' | gtrans }}</a>
                    @endif
                    <div class="nav-tools">
                    <details class="theme-menu" data-theme-menu>
                        <summary class="theme-trigger" data-theme-trigger aria-label="{{ 'nav.theme' | gtrans }}" title="{{ 'nav.theme' | gtrans }}">
                            <svg class="theme-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <g class="theme-icon-auto"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></g>
                                <g class="theme-icon-light"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></g>
                                <g class="theme-icon-dark"><path d="M20.9 13.1A9 9 0 0 1 10.9 3.1 9 9 0 1 0 20.9 13.1Z"/></g>
                            </svg>
                            <svg class="menu-chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="theme-options" role="group" aria-label="{{ 'nav.theme' | gtrans }}">
                            <button class="theme-option" type="button" data-theme-option="auto" aria-pressed="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                                <span class="theme-option-label">{{ 'theme.auto' | gtrans }}</span>
                                <svg class="theme-option-check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m4 12 5 5L20 6"/></svg>
                            </button>
                            <button class="theme-option" type="button" data-theme-option="light" aria-pressed="false">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                                <span class="theme-option-label">{{ 'theme.light' | gtrans }}</span>
                                <svg class="theme-option-check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m4 12 5 5L20 6"/></svg>
                            </button>
                            <button class="theme-option" type="button" data-theme-option="dark" aria-pressed="false">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20.9 13.1A9 9 0 0 1 10.9 3.1 9 9 0 1 0 20.9 13.1Z"/></svg>
                                <span class="theme-option-label">{{ 'theme.dark' | gtrans }}</span>
                                <svg class="theme-option-check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m4 12 5 5L20 6"/></svg>
                            </button>
                        </div>
                    </details>
                    <details class="language-menu" data-language-menu>
                        <summary class="language-trigger" data-language-trigger aria-label="{{ 'nav.language' | gtrans }}" title="{{ 'nav.language' | gtrans }}">
                            <span class="language-glyph" aria-hidden="true"><span>A</span><span>文</span></span>
                            <svg class="menu-chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="language-options" role="group" aria-label="{{ 'nav.language' | gtrans }}">
                            <a class="language-option" href="?lang=pl" lang="pl" @if($locale === 'pl') aria-current="true" @endif>
                                <span class="language-code" aria-hidden="true">PL</span><span class="language-option-label">Polski</span>
                                @if($locale === 'pl')
                                    <svg class="language-option-check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m4 12 5 5L20 6"/></svg>
                                @endif
                            </a>
                            <a class="language-option" href="?lang=en" lang="en" @if($locale === 'en') aria-current="true" @endif>
                                <span class="language-code" aria-hidden="true">EN</span><span class="language-option-label">English</span>
                                @if($locale === 'en')
                                    <svg class="language-option-check" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m4 12 5 5L20 6"/></svg>
                                @endif
                            </a>
                        </div>
                    </details>
                    </div>
                </nav>
            </header>
            <main class="main-content">
                @yield('content')
            </main>
            <footer class="site-footer">
                <span>© 2026 WilyCode</span>
                <a href="https://github.com/wilycode" target="_blank" rel="noopener noreferrer">GitHub ↗</a>
            </footer>
        </div>
        @js('/assets/js/app.js')
        @renderJs()
    </body>
</html>
