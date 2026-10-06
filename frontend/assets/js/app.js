document.addEventListener('DOMContentLoaded', () => {

    /*
    |--------------------------------------------------------------------------
    | Sidebar móvil
    |--------------------------------------------------------------------------
    */

    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const close = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');

    const openSidebar = () => {
        if (!sidebar) return;

        sidebar.classList.add('show');

        if (overlay) {
            overlay.classList.add('show');
        }

        document.body.classList.add('sidebar-open');
    };

    const closeSidebar = () => {
        if (!sidebar) return;

        sidebar.classList.remove('show');

        if (overlay) {
            overlay.classList.remove('show');
        }

        document.body.classList.remove('sidebar-open');
    };

    if (toggle) {
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            openSidebar();
        });
    }

    if (close) {
        close.addEventListener('click', () => {
            closeSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            closeSidebar();
        });
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Cerrar sidebar al seleccionar una opción en móvil
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.sidebar .nav-item').forEach((item) => {
        item.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Mostrar / ocultar contraseña
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {

        button.addEventListener('click', () => {

            const selector = button.getAttribute('data-toggle-password');

            if (!selector) return;

            const input = document.querySelector(selector);

            if (!input) return;

            const isVisible = input.type === 'text';

            input.type = isVisible
                ? 'password'
                : 'text';

            const icon = button.querySelector('i');

            if (icon) {
                icon.className = isVisible
                    ? 'bi bi-eye'
                    : 'bi bi-eye-slash';
            }

            button.setAttribute(
                'aria-label',
                isVisible
                    ? 'Mostrar contraseña'
                    : 'Ocultar contraseña'
            );
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Confirmaciones
    |--------------------------------------------------------------------------
    |
    | Uso:
    |
    | <button
    |   data-confirm="¿Deseas eliminar este registro?">
    |
    */

    document.querySelectorAll('[data-confirm]').forEach((element) => {

        element.addEventListener('click', (event) => {

            const message =
                element.getAttribute('data-confirm')
                || '¿Deseas continuar?';

            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Auto cerrar alertas
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('.app-alert').forEach((alertElement) => {

        setTimeout(() => {

            if (
                typeof bootstrap === 'undefined'
                || !bootstrap.Alert
            ) {
                return;
            }

            try {
                const alert =
                    bootstrap.Alert.getOrCreateInstance(
                        alertElement
                    );

                alert.close();

            } catch (error) {
                console.warn(
                    'No fue posible cerrar la alerta automáticamente.',
                    error
                );
            }

        }, 5000);
    });

    /*
    |--------------------------------------------------------------------------
    | Tooltips Bootstrap
    |--------------------------------------------------------------------------
    */

    if (
        typeof bootstrap !== 'undefined'
        && bootstrap.Tooltip
    ) {

        document
            .querySelectorAll(
                '[data-bs-toggle="tooltip"]'
            )
            .forEach((element) => {

                new bootstrap.Tooltip(element);

            });
    }

    /*
    |--------------------------------------------------------------------------
    | Filtrado simple de tablas
    |--------------------------------------------------------------------------
    |
    | Ejemplo:
    |
    | <input data-table-search="#tablaReservas">
    |
    */

    document
        .querySelectorAll('[data-table-search]')
        .forEach((input) => {

            input.addEventListener('input', () => {

                const selector =
                    input.getAttribute(
                        'data-table-search'
                    );

                if (!selector) return;

                const table =
                    document.querySelector(selector);

                if (!table) return;

                const value =
                    input.value
                        .toLowerCase()
                        .trim();

                table
                    .querySelectorAll('tbody tr')
                    .forEach((row) => {

                        const text =
                            row.textContent
                                .toLowerCase();

                        row.style.display =
                            text.includes(value)
                                ? ''
                                : 'none';
                    });
            });
        });

    /*
    |--------------------------------------------------------------------------
    | Parking Space - interacción visual
    |--------------------------------------------------------------------------
    |
    | Preparado para futuras mejoras:
    |
    | data-space-code="P-01"
    | data-space-status="available"
    |
    */

    document
        .querySelectorAll('.parking-space')
        .forEach((space) => {

            space.addEventListener('click', () => {

                document
                    .querySelectorAll(
                        '.parking-space.selected'
                    )
                    .forEach((selected) => {

                        if (selected !== space) {
                            selected
                                .classList
                                .remove('selected');
                        }
                    });

                space
                    .classList
                    .toggle('selected');
            });
        });

    /*
    |--------------------------------------------------------------------------
    | Formularios - prevenir doble envío
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            'form[data-prevent-double-submit]'
        )
        .forEach((form) => {

            form.addEventListener('submit', () => {

                const submitButton =
                    form.querySelector(
                        '[type="submit"]'
                    );

                if (!submitButton) return;

                submitButton.disabled = true;

                const originalText =
                    submitButton.innerHTML;

                submitButton.dataset.originalText =
                    originalText;

                submitButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
            });
        });

    /*
    |--------------------------------------------------------------------------
    | Año automático
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('[data-current-year]')
        .forEach((element) => {

            element.textContent =
                new Date().getFullYear();

        });

});