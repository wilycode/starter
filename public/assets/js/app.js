(() => {
    'use strict';
    const themeMenu = document.querySelector('[data-theme-menu]');
    if (themeMenu) {
        const trigger = themeMenu.querySelector('[data-theme-trigger]');
        const options = [...themeMenu.querySelectorAll('[data-theme-option]')];
        const themeLabel = themeMenu.querySelector('.theme-options').getAttribute('aria-label');
        const updateSelected = () => {
            const theme = document.documentElement.dataset.theme || 'auto';
            for (const option of options) {
                option.setAttribute('aria-pressed', String(option.dataset.themeOption === theme));
                if (option.dataset.themeOption === theme) {
                    trigger.setAttribute('aria-label', `${themeLabel}: ${option.querySelector('.theme-option-label').textContent.trim()}`);
                }
            }
        };
        updateSelected();

        for (const option of options) {
            option.addEventListener('click', () => {
                const theme = option.dataset.themeOption;
                document.documentElement.dataset.theme = theme;
                try {
                    if (theme === 'auto') localStorage.removeItem('wily-starter.theme');
                    else localStorage.setItem('wily-starter.theme', theme);
                } catch (_) {
                }
                updateSelected();
                window.dispatchEvent(new Event('wily-starter:theme-change'));
                themeMenu.open = false;
                trigger.focus();
            });
        }

        themeMenu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && themeMenu.open) {
                themeMenu.open = false;
                trigger.focus();
                event.preventDefault();
            }
            if (event.key === 'ArrowDown' && document.activeElement === trigger) {
                themeMenu.open = true;
                options[0].focus();
                event.preventDefault();
            }
            if (event.key === 'ArrowUp' && document.activeElement === trigger) {
                themeMenu.open = true;
                options[options.length - 1].focus();
                event.preventDefault();
            }
        });
    }

    const menus = [...document.querySelectorAll('[data-theme-menu], [data-language-menu]')];
    for (const menu of menus) {
        menu.addEventListener('toggle', () => {
            if (menu.open) {
                for (const other of menus) if (other !== menu) other.open = false;
            }
        });
    }
    document.addEventListener('pointerdown', (event) => {
        for (const menu of menus) {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        }
    });
    const languageMenu = document.querySelector('[data-language-menu]');
    if (languageMenu) {
        const trigger = languageMenu.querySelector('[data-language-trigger]');
        const links = [...languageMenu.querySelectorAll('.language-option')];
        languageMenu.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && languageMenu.open) {
                languageMenu.open = false;
                trigger.focus();
                event.preventDefault();
            }
            if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && document.activeElement === trigger) {
                languageMenu.open = true;
                links[event.key === 'ArrowDown' ? 0 : links.length - 1].focus();
                event.preventDefault();
            }
        });
    }

    const errorSummary = document.querySelector('[data-error-summary]');
    if (errorSummary) errorSummary.focus();

    document.querySelectorAll('form[data-prevent-double-submit]').forEach((form) => {
        form.addEventListener('submit', () => {
            const submit = form.querySelector('[type="submit"]');
            if (submit) submit.disabled = true;
        });
    });
})();
