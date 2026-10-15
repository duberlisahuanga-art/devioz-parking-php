/* Refresh admin data without reloading the document or replacing an edited form. */
(() => {
    const region = document.querySelector('[data-live-reservations]');
    const map = document.querySelector('[data-live-parking]');
    const status = document.querySelector('[data-live-status]');
    if (!region && !map) return;
    let busy = false, submitted = false, controller;
    document.addEventListener('input', event => {
        if (region?.contains(event.target)) event.target.closest('form')?.setAttribute('data-live-dirty', 'true');
    });
    document.addEventListener('submit', () => {
        submitted = true;
        controller?.abort();
    }, true);
    async function refresh() {
        if (busy || submitted || document.hidden || document.querySelector('.modal.show')) return;
        if (region?.querySelector('[data-live-dirty]') || region?.contains(document.activeElement)
            && document.activeElement.matches('input,textarea,select')) return;
        busy = true;
        controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(location.href, { signal: controller.signal, cache: 'no-store' });
            if (!response.ok || new URL(response.url).pathname !== location.pathname) throw new Error();
            const documentNext = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (region) {
                const next = documentNext.querySelector('[data-live-reservations]');
                if (!next) throw new Error();
                if (region.innerHTML !== next.innerHTML) region.innerHTML = next.innerHTML;
            }
            if (map) {
                document.querySelectorAll('.js-admin-space').forEach(button => {
                    const next = [...documentNext.querySelectorAll('.js-admin-space')]
                        .find(item => item.dataset.spaceCode === button.dataset.spaceCode);
                    if (!next) return;
                    const selected = button.classList.contains('selected');
                    if (button.innerHTML !== next.innerHTML || JSON.stringify(button.dataset) !== JSON.stringify(next.dataset)) {
                        [...next.attributes].forEach(attr => button.setAttribute(attr.name, attr.value));
                        button.innerHTML = next.innerHTML;
                        if (selected) { button.classList.add('selected'); button.click(); }
                    }
                });
                for (const selector of ['.spaces-kpi-card', '.summary-chip', '.spaces-total-badge']) {
                    const next = documentNext.querySelectorAll(selector);
                    document.querySelectorAll(selector).forEach((item, index) => {
                        if (next[index] && item.innerHTML !== next[index].innerHTML) item.innerHTML = next[index].innerHTML;
                    });
                }
            }
            if (status) status.textContent = 'Datos actualizados · Actualización automática cada 30 segundos';
        } catch {
            if (status && !submitted) status.textContent = 'No se pudo actualizar. Reintentando automáticamente…';
        } finally {
            clearTimeout(timeout);
            busy = false;
        }
    }
    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
