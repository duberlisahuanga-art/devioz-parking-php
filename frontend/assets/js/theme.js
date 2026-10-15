/* Apply before stylesheets to avoid a light frame when dark is preferred. */
(() => {
    const documentRoot = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const choices = ['light', 'dark', 'system'];
    let preference = 'light';
    try { preference = localStorage.getItem('devioz_theme') || 'light'; } catch { /* Use the default when storage is unavailable. */ }
    if (!choices.includes(preference)) preference = 'light';
    const apply = (choice, persist = false) => {
        preference = choices.includes(choice) ? choice : 'light';
        const actual = preference === 'system' ? (media.matches ? 'dark' : 'light') : preference;
        documentRoot.dataset.theme = actual;
        documentRoot.dataset.bsTheme = actual;
        documentRoot.dataset.themePreference = preference;
        if (persist) { try { localStorage.setItem('devioz_theme', preference); } catch { /* Current-page controls remain usable. */ } }
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.querySelector('i').className = actual === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
            const label = actual === 'dark' ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
            button.setAttribute('aria-label', label);
            button.title = label;
        });
        document.querySelectorAll('[data-theme-choice]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === preference)));
    };
    apply(preference);
    media.addEventListener('change', () => { if (preference === 'system') apply(preference); });
    window.addEventListener('storage', (event) => { if (event.key === 'devioz_theme') apply(event.newValue || 'light'); });
    document.addEventListener('DOMContentLoaded', () => {
        apply(preference);
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.addEventListener('click', () => apply(documentRoot.dataset.theme === 'dark' ? 'light' : 'dark', true)));
        document.querySelectorAll('[data-theme-choice]').forEach((button) => button.addEventListener('click', () => apply(button.dataset.themeChoice, true)));
    });
})();
