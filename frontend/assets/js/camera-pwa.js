(() => {
    'use strict';
    const script = document.querySelector('script[data-camera-pwa]');
    if (!script) return;
    const button = document.querySelector('[data-pwa-install]');
    const status = document.querySelector('[data-pwa-status]');
    let pendingPrompt = null;
    const installed = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const say = message => { if (status) { status.hidden = false; status.textContent = message; } };
    const hide = () => { if (button) button.hidden = true; pendingPrompt = null; };
    if (!window.isSecureContext) {
        say('Para instalar Devioz en el teléfono, abre la dirección HTTPS del sistema.');
        return;
    }
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(script.dataset.worker, { updateViaCache: 'none' })
            .catch(() => say('No se pudo preparar la instalación. Puedes seguir utilizando el navegador.'));
    }
    if (!button || installed()) return;
    const appleMobile = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (appleMobile) button.hidden = false;
    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        pendingPrompt = event;
        if (!installed()) button.hidden = false;
    });
    button.addEventListener('click', async () => {
        if (appleMobile && !pendingPrompt) {
            say('En el menú Compartir del navegador, selecciona “Añadir a pantalla de inicio”.');
            return;
        }
        const prompt = pendingPrompt;
        if (!prompt) return;
        hide();
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            say(choice.outcome === 'accepted' ? 'Instalación solicitada. Puedes abrir Devioz desde su icono.' : 'Puedes seguir usando Devioz desde el navegador.');
        } catch { say('Puedes instalar Devioz desde el menú de tu navegador.'); }
    });
    window.addEventListener('appinstalled', () => { hide(); say('Devioz está instalado en este dispositivo.'); });
})();
