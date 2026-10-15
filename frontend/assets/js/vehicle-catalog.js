/* Local catalog assistance; the server validates the submitted make/model pair. */
(() => {
    const form = document.querySelector('[data-vehicle-catalog]');
    if (!form) return;
    const type = form.elements.type;
    const brand = form.elements.brand;
    const model = form.elements.model;
    const hint = document.getElementById('vehicle-catalog-hint');
    const modelHint = document.getElementById('vehicle-model-hint');
    const manual = form.elements.catalog_manual;
    let makeId = null;

    function autocomplete(input, kind, selected) {
        const list = document.getElementById(input.getAttribute('aria-controls'));
        const status = document.getElementById(`${input.id}-status`);
        let timer, controller, sequence = 0, items = [], active = -1;
        const close = () => {
            clearTimeout(timer);
            controller?.abort();
            sequence++;
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            input.removeAttribute('aria-busy');
            status.textContent = '';
            active = -1;
        };
        const choose = index => {
            const item = items[index];
            if (!item) return;
            input.value = item.name;
            close();
            selected(item);
        };
        async function search() {
            close();
            if (type.value !== 'car' || (kind === 'models' && !makeId)) return;
            const current = ++sequence;
            controller = new AbortController();
            input.setAttribute('aria-busy', 'true');
            status.textContent = 'Buscando sugerencias…';
            const url = new URL(form.dataset.vehicleCatalog, location.href);
            url.search = new URLSearchParams({ kind, type: type.value, q: input.value.trim(),
                ...(kind === 'models' ? { make_id: makeId } : {}) });
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error();
                const data = await response.json();
                if (current !== sequence || document.activeElement !== input) return;
                if (!Array.isArray(data.items)) throw new Error();
                items = data.items;
                list.replaceChildren();
                items.forEach((item, index) => {
                    const option = document.createElement('li');
                    option.id = `${input.id}-option-${index}`;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.textContent = item.name;
                    option.addEventListener('pointerdown', event => event.preventDefault());
                    option.addEventListener('click', () => choose(index));
                    list.append(option);
                });
                list.hidden = items.length === 0;
                input.setAttribute('aria-expanded', String(items.length > 0));
                status.textContent = items.length ? `${items.length} sugerencias. Usa las flechas y Enter para elegir.`
                    : 'Sin coincidencias. Si tu vehículo no está listado, activa la opción de entrada manual.';
            } catch (error) {
                if (current !== sequence || error.name === 'AbortError') return;
                status.textContent = 'Sugerencias no disponibles. Para datos no listados, activa la opción de entrada manual.';
            } finally {
                if (current === sequence) input.removeAttribute('aria-busy');
            }
        }
        input.addEventListener('input', () => {
            close();
            timer = setTimeout(search, 250);
        });
        input.addEventListener('focus', search);
        input.addEventListener('blur', close);
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { close(); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.hidden) { search(); return; }
                active = (active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                [...list.children].forEach((option, i) => option.setAttribute('aria-selected', String(i === active)));
                input.setAttribute('aria-activedescendant', list.children[active].id);
                list.children[active].scrollIntoView({ block: 'nearest' });
            } else if (event.key === 'Enter' && !list.hidden && active >= 0) {
                event.preventDefault();
                choose(active);
            }
        });
        document.addEventListener('pointerdown', event => {
            if (!input.parentElement.contains(event.target)) close();
        });
        return { close };
    }

    function updateModelHint() {
        modelHint.textContent = type.value === 'moto' ? 'Escribe el modelo de tu moto.'
            : manual.checked ? 'Escribe el modelo no listado. Se comprobará que no corresponda a otra marca conocida.'
            : makeId ? 'Selecciona un modelo de esta marca. Para uno no listado, activa la opción de entrada manual.'
            : 'Selecciona primero una marca para ver sus modelos.';
    }
    const models = autocomplete(model, 'models', () => {});
    const brands = autocomplete(brand, 'makes', item => {
        makeId = item.id;
        model.value = '';
        models.close();
        updateModelHint();
        model.focus();
    });
    brand.addEventListener('input', () => {
        makeId = null;
        model.value = '';
        models.close();
        updateModelHint();
    });
    type.addEventListener('change', () => {
        brands.close();
        models.close();
        makeId = null;
        manual.checked = false;
        brand.value = '';
        model.value = '';
        hint.textContent = type.value === 'moto'
            ? 'Para motos, escribe marca y modelo manualmente. Este catálogo no incluye motocicletas.'
            : 'Marcas frecuentes en Perú primero. Selecciona la marca y su modelo; puedes buscar otras marcas escribiendo.';
        updateModelHint();
    });
    manual.addEventListener('change', updateModelHint);
    updateModelHint();
})();
