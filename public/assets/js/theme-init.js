(() => {
    'use strict';
    let theme = 'auto';
    try {
        const saved = localStorage.getItem('wily-starter.theme');
        if (saved === 'light' || saved === 'dark') theme = saved;
    } catch (_) {
    }
    // Run before the stylesheet loads to avoid a flash of the wrong theme.
    document.documentElement.dataset.theme = theme;

    const favicon = document.getElementById('theme-favicon');
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    const syncIcon = () => {
        if (!favicon) return;
        const dark = document.documentElement.dataset.theme === 'dark'
            || (document.documentElement.dataset.theme === 'auto' && system.matches);
        favicon.href = dark ? favicon.dataset.darkIcon : favicon.dataset.lightIcon;
    };
    syncIcon();
    if (system.addEventListener) system.addEventListener('change', syncIcon);
    window.addEventListener('wily-starter:theme-change', syncIcon);
})();
