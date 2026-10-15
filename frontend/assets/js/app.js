document.addEventListener('DOMContentLoaded', () => {
    // Filtros del mapa del dashboard; independientes del formulario de reservas.
    document.querySelectorAll('[data-dashboard-map]').forEach((map) => {
        const buttons = map.querySelectorAll('[data-dashboard-filter]');
        const spaces = map.querySelectorAll('[data-dashboard-space]');
        const empty = map.querySelector('[data-dashboard-empty]');
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const status = button.dataset.dashboardFilter;
                let visible = 0;
                spaces.forEach((space) => {
                    space.hidden = status !== 'all' && space.dataset.dashboardSpace !== status;
                    if (!space.hidden) visible++;
                });
                buttons.forEach((item) => {
                    const selected = item === button;
                    item.setAttribute('aria-pressed', String(selected));
                    item.classList.toggle('active', selected);
                });
                if (empty) empty.hidden = visible > 0;
            });
        });
    });

    /*
|--------------------------------------------------------------------------
| RESERVAR PLAZA - CONDUCTOR
|--------------------------------------------------------------------------
*/

const reservationForm =
    document.getElementById('reservationForm');

if (reservationForm) {

    const spaceIdInput =
        document.getElementById('spaceId');

    const vehicleSelect =
        document.getElementById('vehicleSelect');

    const startsOnInput =
        document.getElementById('startsOn');

    const startDateDisplay =
        document.getElementById('startDateDisplay');

    const endDateDisplay =
        document.getElementById('endDateDisplay');

    const summarySpace =
        document.getElementById('summarySpace');

    const summaryVehicle =
        document.getElementById('summaryVehicle');

    const confirmButton =
        document.getElementById('confirmReservationBtn');

    const spaceCards =
        document.querySelectorAll('.space-card');

    const filterButtons =
        document.querySelectorAll('.space-filter');

    /*
    |--------------------------------------------------------------------------
    | Formatear fecha dd/mm/yyyy
    |--------------------------------------------------------------------------
    */

    const formatReservationDate = (date) => {

        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return 'Selecciona una fecha';
        }

        const day =
            String(date.getDate()).padStart(2, '0');

        const month =
            String(date.getMonth() + 1).padStart(2, '0');

        const year =
            date.getFullYear();

        return `${day}/${month}/${year}`;
    };

    /*
    |--------------------------------------------------------------------------
    | Convertir YYYY-MM-DD a Date local
    |--------------------------------------------------------------------------
    |
    | Evitamos new Date("2026-10-08") directamente porque puede producir
    | diferencias de zona horaria en algunos navegadores.
    |--------------------------------------------------------------------------
    */

    const parseLocalDate = (value) => {

        if (!value) {
            return null;
        }

        const parts = value.split('-');

        if (parts.length !== 3) {
            return null;
        }

        const year = Number(parts[0]);
        const month = Number(parts[1]);
        const day = Number(parts[2]);

        if (
            !Number.isInteger(year)
            || !Number.isInteger(month)
            || !Number.isInteger(day)
        ) {
            return null;
        }

        const date = new Date(
            year,
            month - 1,
            day
        );

        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return date;
    };

    /*
    |--------------------------------------------------------------------------
    | Calcular vencimiento mensual
    |--------------------------------------------------------------------------
    |
    | Regla de negocio:
    |
    | inicio 08/10/2026
    | fin    07/11/2026
    |
    | Equivalente conceptualmente a:
    | addMonthNoOverflow()->subDay()
    |
    |--------------------------------------------------------------------------
    */

    const calculateMonthlyEndDate = (value) => {

        const startDate =
            parseLocalDate(value);

        if (!startDate) {
            return null;
        }

        const year =
            startDate.getFullYear();

        const month =
            startDate.getMonth();

        const originalDay =
            startDate.getDate();

        /*
        |--------------------------------------------------------------------------
        | Nos movemos al mes siguiente.
        |--------------------------------------------------------------------------
        */

        const targetMonthFirstDay =
            new Date(
                year,
                month + 1,
                1
            );

        const targetYear =
            targetMonthFirstDay.getFullYear();

        const targetMonth =
            targetMonthFirstDay.getMonth();

        /*
        |--------------------------------------------------------------------------
        | Último día válido del mes siguiente.
        |--------------------------------------------------------------------------
        */

        const lastDayOfTargetMonth =
            new Date(
                targetYear,
                targetMonth + 1,
                0
            ).getDate();

        /*
        |--------------------------------------------------------------------------
        | Simula addMonthNoOverflow().
        |--------------------------------------------------------------------------
        */

        const safeDay =
            Math.min(
                originalDay,
                lastDayOfTargetMonth
            );

        const endDate =
            new Date(
                targetYear,
                targetMonth,
                safeDay
            );

        /*
        |--------------------------------------------------------------------------
        | La mensualidad termina un día antes.
        |--------------------------------------------------------------------------
        */

        endDate.setDate(
            endDate.getDate() - 1
        );

        return endDate;
    };

    /*
    |--------------------------------------------------------------------------
    | Obtener texto del vehículo seleccionado
    |--------------------------------------------------------------------------
    */

    const getSelectedVehicleText = () => {

        if (!vehicleSelect) {
            return 'Sin seleccionar';
        }

        const option =
            vehicleSelect.options[
                vehicleSelect.selectedIndex
            ];

        if (
            !option
            || vehicleSelect.value === ''
        ) {
            return 'Sin seleccionar';
        }

        return option.textContent.trim();
    };

    /*
    |--------------------------------------------------------------------------
    | Actualizar fechas
    |--------------------------------------------------------------------------
    */

    const updateReservationDates = () => {

        if (!startsOnInput) {
            return;
        }

        const value =
            startsOnInput.value;

        const startDate =
            parseLocalDate(value);

        const endDate =
            calculateMonthlyEndDate(value);

        if (startDateDisplay) {

            startDateDisplay.textContent =
                startDate
                    ? formatReservationDate(startDate)
                    : 'Selecciona una fecha';
        }

        if (endDateDisplay) {

            endDateDisplay.textContent =
                endDate
                    ? formatReservationDate(endDate)
                    : 'Selecciona una fecha';
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Actualizar resumen del vehículo
    |--------------------------------------------------------------------------
    */

    const updateVehicleSummary = () => {

        if (!summaryVehicle) {
            return;
        }

        summaryVehicle.textContent =
            getSelectedVehicleText();
    };

    /*
    |--------------------------------------------------------------------------
    | Validar si se puede confirmar
    |--------------------------------------------------------------------------
    */

    const updateConfirmButton = () => {

        if (!confirmButton) {
            return;
        }

        const hasSpace =
            spaceIdInput
            && spaceIdInput.value !== '';

        const hasVehicle =
            vehicleSelect
            && vehicleSelect.value !== '';

        const hasDate =
            startsOnInput
            && startsOnInput.value !== '';

        confirmButton.disabled =
            !(
                hasSpace
                && hasVehicle
                && hasDate
            );
    };

    /*
    |--------------------------------------------------------------------------
    | Seleccionar plaza
    |--------------------------------------------------------------------------
    */

    spaceCards.forEach((card) => {

        card.addEventListener(
            'click',
            () => {

                const status =
                    card.dataset.spaceStatus
                    || '';

                /*
                |--------------------------------------------------------------------------
                | Solo plazas available.
                |--------------------------------------------------------------------------
                */

                if (
                    status !== 'available'
                    || card.disabled
                ) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Quitar selección anterior.
                |--------------------------------------------------------------------------
                */

                spaceCards.forEach(
                    (otherCard) => {

                        otherCard.classList.remove(
                            'is-selected',
                            'selected'
                        );

                        otherCard.removeAttribute(
                            'aria-pressed'
                        );
                    }
                );

                /*
                |--------------------------------------------------------------------------
                | Seleccionar actual.
                |--------------------------------------------------------------------------
                */

                card.classList.add(
                    'is-selected',
                    'selected'
                );

                card.setAttribute(
                    'aria-pressed',
                    'true'
                );

                const spaceId =
                    card.dataset.spaceId
                    || '';

                const spaceCode =
                    card.dataset.spaceCode
                    || 'Sin seleccionar';

                if (spaceIdInput) {

                    spaceIdInput.value =
                        spaceId;
                }

                if (summarySpace) {

                    summarySpace.textContent =
                        spaceCode;
                }

                updateConfirmButton();
            }
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Selector de vehículo
    |--------------------------------------------------------------------------
    */

    if (vehicleSelect) {

        vehicleSelect.addEventListener(
            'change',
            () => {

                updateVehicleSummary();
                updateConfirmButton();
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fecha de inicio
    |--------------------------------------------------------------------------
    */

    if (startsOnInput) {

        startsOnInput.addEventListener(
            'change',
            () => {

                updateReservationDates();
                updateConfirmButton();
            }
        );

        startsOnInput.addEventListener(
            'input',
            () => {

                updateReservationDates();
                updateConfirmButton();
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Filtros de plazas
    |--------------------------------------------------------------------------
    */

    filterButtons.forEach((button) => {

        button.addEventListener(
            'click',
            () => {

                const filter =
                    button.dataset.filter
                    || 'all';

                /*
                |--------------------------------------------------------------------------
                | Estado visual del filtro.
                |--------------------------------------------------------------------------
                */

                filterButtons.forEach(
                    (otherButton) => {

                        otherButton.classList.remove(
                            'is-active'
                        );
                    }
                );

                button.classList.add(
                    'is-active'
                );

                /*
                |--------------------------------------------------------------------------
                | Mostrar / ocultar plazas.
                |--------------------------------------------------------------------------
                */

                spaceCards.forEach((card) => {

                    const status =
                        card.dataset
                            .filterMatch
                        || card.dataset
                            .spaceStatus
                        || '';

                    const shouldShow =
                        filter === 'all'
                        || status === filter;

                    card.style.display =
                        shouldShow
                            ? ''
                            : 'none';
                });
            }
        );
    });

    /*
    |--------------------------------------------------------------------------
    | Validación final del formulario
    |--------------------------------------------------------------------------
    */

    reservationForm.addEventListener(
        'submit',
        (event) => {

            const hasSpace =
                spaceIdInput
                && spaceIdInput.value !== '';

            const hasVehicle =
                vehicleSelect
                && vehicleSelect.value !== '';

            const hasDate =
                startsOnInput
                && startsOnInput.value !== '';

            if (!hasSpace) {

                event.preventDefault();

                window.alert(
                    'Selecciona una plaza disponible.'
                );

                return;
            }

            if (!hasVehicle) {

                event.preventDefault();

                window.alert(
                    'Selecciona un vehículo.'
                );

                vehicleSelect?.focus();

                return;
            }

            if (!hasDate) {

                event.preventDefault();

                window.alert(
                    'Selecciona la fecha de inicio.'
                );

                startsOnInput?.focus();
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Inicialización
    |--------------------------------------------------------------------------
    */

    updateVehicleSummary();
    updateReservationDates();
    updateConfirmButton();
}
    /*
|--------------------------------------------------------------------------
| Visor interno de comprobantes
|--------------------------------------------------------------------------
*/

const voucherModal =
    document.getElementById('voucherModal');

if (voucherModal) {

    const voucherImageContainer =
        document.getElementById(
            'voucherImageContainer'
        );

    const voucherPdfContainer =
        document.getElementById(
            'voucherPdfContainer'
        );

    const voucherUnsupported =
        document.getElementById(
            'voucherUnsupported'
        );

    const voucherImage =
        document.getElementById(
            'voucherImage'
        );

    const voucherPdf =
        document.getElementById(
            'voucherPdf'
        );

    const voucherReservationId =
        document.getElementById(
            'voucherReservationId'
        );

    const voucherSpaceCode =
        document.getElementById(
            'voucherSpaceCode'
        );

    document
        .querySelectorAll(
            '.js-view-voucher'
        )
        .forEach((button) => {

            button.addEventListener(
                'click',
                () => {

                    const url =
                        button.dataset.voucherUrl
                        || '';

                    const type =
                        (
                            button.dataset.voucherType
                            || ''
                        ).toLowerCase();

                    const reservationId =
                        button.dataset.reservationId
                        || '—';

                    const spaceCode =
                        button.dataset.spaceCode
                        || '—';

                    if (voucherReservationId) {
                        voucherReservationId.textContent =
                            `#${reservationId}`;
                    }

                    if (voucherSpaceCode) {
                        voucherSpaceCode.textContent =
                            `Plaza ${spaceCode}`;
                    }

                    voucherImageContainer
                        ?.classList
                        .add('d-none');

                    voucherPdfContainer
                        ?.classList
                        .add('d-none');

                    voucherUnsupported
                        ?.classList
                        .add('d-none');

                    if (
                        ['jpg', 'jpeg', 'png', 'webp']
                            .includes(type)
                    ) {

                        if (voucherImage) {
                            voucherImage.src = url;
                        }

                        voucherImageContainer
                            ?.classList
                            .remove('d-none');

                    } else if (type === 'pdf') {

                        if (voucherPdf) {
                            voucherPdf.src = url;
                        }

                        voucherPdfContainer
                            ?.classList
                            .remove('d-none');

                    } else {

                        voucherUnsupported
                            ?.classList
                            .remove('d-none');
                    }
                }
            );
        });

    voucherModal.addEventListener(
        'hidden.bs.modal',
        () => {

            if (voucherImage) {
                voucherImage.src = '';
            }

            if (voucherPdf) {
                voucherPdf.src = '';
            }

            voucherImageContainer
                ?.classList
                .add('d-none');

            voucherPdfContainer
                ?.classList
                .add('d-none');

            voucherUnsupported
                ?.classList
                .add('d-none');
        }
    );
}
    /*
    |--------------------------------------------------------------------------
    | Sidebar móvil
    |--------------------------------------------------------------------------
    */

    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const close = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    const collapseToggle = document.getElementById('sidebarCollapseToggle');
    const sidebarStorageKey = 'devioz_sidebar_collapsed';
    const isDesktopSidebar = () => window.innerWidth >= 992;
    let preferredSidebarCollapsed = false;

    if (sidebar && collapseToggle) {
        try {
            preferredSidebarCollapsed =
                localStorage.getItem(sidebarStorageKey) === '1';
        } catch {
            // El menú sigue funcionando si el navegador bloquea el almacenamiento.
        }
    }

    const updateSidebarCollapseUI = () => {
        if (!collapseToggle) return;

        const collapsed = document.body.classList.contains('sidebar-collapsed');
        const label = collapsed
            ? 'Expandir menú lateral'
            : 'Minimizar menú lateral';

        collapseToggle.setAttribute('aria-expanded', String(!collapsed));
        collapseToggle.setAttribute('aria-label', label);
        collapseToggle.title = label;

        const icon = collapseToggle.querySelector('i');
        if (icon) {
            icon.className = collapsed
                ? 'bi bi-chevron-right'
                : 'bi bi-chevron-left';
        }
    };

    const setSidebarCollapsed = (collapsed, persist = true) => {
        if (!sidebar || !collapseToggle) return;

        document.body.classList.toggle(
            'sidebar-collapsed',
            isDesktopSidebar() && collapsed
        );
        updateSidebarCollapseUI();

        if (persist) {
            preferredSidebarCollapsed = collapsed;
            try {
                localStorage.setItem(sidebarStorageKey, collapsed ? '1' : '0');
            } catch {
                // Conserva la preferencia en memoria durante esta página.
            }
        }
    };

    setSidebarCollapsed(preferredSidebarCollapsed, false);

    if (collapseToggle) {
        collapseToggle.addEventListener('click', () => {
            if (!isDesktopSidebar()) return;
            setSidebarCollapsed(
                !document.body.classList.contains('sidebar-collapsed')
            );
        });
    }

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

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', () => {
        if (isDesktopSidebar()) {
            closeSidebar();
        }
        setSidebarCollapsed(preferredSidebarCollapsed, false);
    });

    document
        .querySelectorAll('.sidebar .nav-item')
        .forEach((item) => {

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

    document
        .querySelectorAll('[data-toggle-password]')
        .forEach((button) => {

            button.addEventListener('click', () => {

                const selector =
                    button.getAttribute(
                        'data-toggle-password'
                    );

                if (!selector) return;

                const input =
                    document.querySelector(selector);

                if (!input) return;

                const isVisible =
                    input.type === 'text';

                input.type =
                    isVisible
                        ? 'password'
                        : 'text';

                const icon =
                    button.querySelector('i');

                if (icon) {

                    icon.className =
                        isVisible
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
    */

    document
        .querySelectorAll('[data-confirm]')
        .forEach((element) => {

            element.addEventListener(
                'click',
                (event) => {

                    const message =
                        element.getAttribute(
                            'data-confirm'
                        )
                        || '¿Deseas continuar?';

                    if (!window.confirm(message)) {

                        event.preventDefault();
                        event.stopPropagation();

                    }
                }
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Auto cerrar alertas
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.app-alert')
        .forEach((alertElement) => {

            setTimeout(() => {

                if (
                    typeof bootstrap === 'undefined'
                    || !bootstrap.Alert
                ) {
                    return;
                }

                try {

                    const alert =
                        bootstrap.Alert
                            .getOrCreateInstance(
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

                new bootstrap.Tooltip(
                    element
                );

            });
    }

    /*
    |--------------------------------------------------------------------------
    | Filtrado simple de tablas
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('[data-table-search]')
        .forEach((input) => {

            input.addEventListener(
                'input',
                () => {

                    const selector =
                        input.getAttribute(
                            'data-table-search'
                        );

                    if (!selector) return;

                    const table =
                        document.querySelector(
                            selector
                        );

                    if (!table) return;

                    const value =
                        input.value
                            .toLowerCase()
                            .trim();

                    table
                        .querySelectorAll(
                            'tbody tr'
                        )
                        .forEach((row) => {

                            const text =
                                row.textContent
                                    .toLowerCase();

                            row.style.display =
                                text.includes(value)
                                    ? ''
                                    : 'none';
                        });
                }
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Parking Space
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.parking-space')
        .forEach((space) => {

            space.addEventListener(
                'click',
                () => {

                    document
                        .querySelectorAll(
                            '.parking-space.selected'
                        )
                        .forEach(
                            (selected) => {

                                if (
                                    selected !== space
                                ) {

                                    selected
                                        .classList
                                        .remove(
                                            'selected'
                                        );
                                }
                            }
                        );

                    space
                        .classList
                        .toggle(
                            'selected'
                        );
                }
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Prevenir doble envío
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            'form[data-prevent-double-submit]'
        )
        .forEach((form) => {

            form.addEventListener(
                'submit',
                () => {

                    const submitButton =
                        form.querySelector(
                            '[type="submit"]'
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Algunos formularios, como el modal de pago,
                    | tienen el botón submit fuera del <form>.
                    |--------------------------------------------------------------------------
                    */

                    let externalButton = null;

                    if (
                        form.id
                        && !submitButton
                    ) {

                        externalButton =
                            document.querySelector(
                                `[type="submit"][form="${form.id}"]`
                            );
                    }

                    const button =
                        submitButton
                        || externalButton;

                    if (!button) return;

                    button.disabled = true;

                    if (
                        !button.dataset.originalText
                    ) {

                        button.dataset.originalText =
                            button.innerHTML;
                    }

                    button.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
                }
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Año automático
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '[data-current-year]'
        )
        .forEach((element) => {

            element.textContent =
                new Date().getFullYear();

        });

    /*
    |--------------------------------------------------------------------------
    | Modal de pago Yape
    |--------------------------------------------------------------------------
    */

    const paymentModal =
        document.getElementById(
            'paymentModal'
        );

    if (paymentModal) {

        const reservationInput =
            document.getElementById(
                'paymentReservationId'
            );

        const amountElement =
            document.getElementById(
                'paymentModalAmount'
            );

        const qrAmountElement =
            document.getElementById(
                'paymentQRAmount'
            );

        const spaceElement =
            document.getElementById(
                'paymentSpaceCode'
            );

        const vehicleElement =
            document.getElementById(
                'paymentVehiclePlate'
            );

        const operationInput =
            document.getElementById(
                'paymentOperation'
            );

        const voucherInput =
            document.getElementById(
                'paymentVoucher'
            );

        const paymentForm =
            document.getElementById(
                'paymentVoucherForm'
            );

        const submitButton =
            document.getElementById(
                'paymentSubmitButton'
            );

        /*
        |--------------------------------------------------------------------------
        | Abrir modal con datos de la reserva
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.js-open-payment-modal'
            )
            .forEach((button) => {

                button.addEventListener(
                    'click',
                    () => {

                        const reservationId =
                            button.dataset
                                .reservationId
                            || '';

                        const spaceCode =
                            button.dataset
                                .spaceCode
                            || '—';

                        const vehiclePlate =
                            button.dataset
                                .vehiclePlate
                            || '—';

                        const amount =
                            Number.parseFloat(
                                button.dataset
                                    .amount
                                || '0'
                            );

                        const validAmount =
                            Number.isFinite(amount)
                                ? amount
                                : 0;

                        const formattedAmount =
                            `S/ ${validAmount.toFixed(2)}`;

                        if (
                            reservationInput
                        ) {

                            reservationInput.value =
                                reservationId;
                        }

                        if (spaceElement) {

                            spaceElement.textContent =
                                spaceCode;
                        }

                        if (vehicleElement) {

                            vehicleElement.textContent =
                                vehiclePlate;
                        }

                        if (amountElement) {

                            amountElement.textContent =
                                formattedAmount;
                        }

                        if (qrAmountElement) {

                            qrAmountElement.textContent =
                                formattedAmount;
                        }

                        if (operationInput) {

                            operationInput.value =
                                '';
                        }

                        if (voucherInput) {

                            voucherInput.value =
                                '';
                        }

                        if (submitButton) {

                            submitButton.disabled =
                                false;

                            if (
                                submitButton
                                    .dataset
                                    .originalText
                            ) {

                                submitButton.innerHTML =
                                    submitButton
                                        .dataset
                                        .originalText;
                            }
                        }
                    }
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Validar archivo antes de enviar
        |--------------------------------------------------------------------------
            */

        if (voucherInput) {

            voucherInput.addEventListener(
                'change',
                () => {

                    const file =
                        voucherInput.files
                            ? voucherInput.files[0]
                            : null;

                    if (!file) {
                        return;
                    }

                    const maxSize =
                        5 * 1024 * 1024;

                    const allowedTypes = [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ];

                    if (
                        file.size > maxSize
                    ) {

                        window.alert(
                            'El comprobante no puede superar 5 MB.'
                        );

                        voucherInput.value =
                            '';

                        return;
                    }

                    if (
                        !allowedTypes.includes(
                            file.type
                        )
                    ) {

                        window.alert(
                            'Formato no permitido. Usa JPG, PNG, WEBP o PDF.'
                        );

                        voucherInput.value =
                            '';
                    }
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validación antes de enviar pago
        |--------------------------------------------------------------------------
        */

        if (paymentForm) {

            paymentForm.addEventListener(
                'submit',
                (event) => {

                    const reservationId =
                        reservationInput
                            ? reservationInput
                                .value
                                .trim()
                            : '';

                    const operation =
                        operationInput
                            ? operationInput
                                .value
                                .trim()
                            : '';

                    const file =
                        voucherInput
                        && voucherInput.files
                            ? voucherInput
                                .files[0]
                            : null;

                    if (
                        reservationId === ''
                    ) {

                        event.preventDefault();

                        window.alert(
                            'No se pudo identificar la reserva seleccionada.'
                        );

                        return;
                    }

                    if (
                        operation === ''
                    ) {

                        event.preventDefault();

                        window.alert(
                            'Ingresa el número de operación de Yape.'
                        );

                        operationInput
                            ?.focus();

                        return;
                    }

                    if (!file) {

                        event.preventDefault();

                        window.alert(
                            'Adjunta el comprobante de pago.'
                        );

                        voucherInput
                            ?.focus();

                        return;
                    }
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Limpiar modal al cerrar
        |--------------------------------------------------------------------------
        */

        paymentModal.addEventListener(
            'hidden.bs.modal',
            () => {

                if (
                    reservationInput
                ) {

                    reservationInput.value =
                        '';
                }

                if (
                    operationInput
                ) {

                    operationInput.value =
                        '';
                }

                if (
                    voucherInput
                ) {

                    voucherInput.value =
                        '';
                }

                if (
                    spaceElement
                ) {

                    spaceElement.textContent =
                        '—';
                }

                if (
                    vehicleElement
                ) {

                    vehicleElement.textContent =
                        '—';
                }

                if (
                    amountElement
                ) {

                    amountElement.textContent =
                        'S/ 0.00';
                }

                if (
                    qrAmountElement
                ) {

                    qrAmountElement.textContent =
                        'S/ 0.00';
                }

                if (
                    submitButton
                ) {

                    submitButton.disabled =
                        false;

                    if (
                        submitButton
                            .dataset
                            .originalText
                    ) {

                        submitButton.innerHTML =
                            submitButton
                                .dataset
                                .originalText;
                    }
                }
            }
        );
    }

/*
|--------------------------------------------------------------------------
| ADMIN - CROQUIS DE ESPACIOS
|--------------------------------------------------------------------------
*/

const adminSpaces =
    document.querySelectorAll('.js-admin-space');

if (adminSpaces.length > 0) {

    const detailSpaceCode =
        document.getElementById('detailSpaceCode');

    const detailSpaceStatus =
        document.getElementById('detailSpaceStatus');

    const spaceDetailEmpty =
        document.getElementById('spaceDetailEmpty');

    const spaceAvailableMessage =
        document.getElementById('spaceAvailableMessage');

    const spaceDetailContent =
        document.getElementById('spaceDetailContent');

    const spaceDetailFooter =
        document.getElementById('spaceDetailFooter');

    const detailDriverName =
        document.getElementById('detailDriverName');

    const detailDriverEmail =
        document.getElementById('detailDriverEmail');

    const detailDriverPhone =
        document.getElementById('detailDriverPhone');

    const detailVehiclePlate =
        document.getElementById('detailVehiclePlate');

    const detailVehicleModel =
        document.getElementById('detailVehicleModel');

    const detailVehicleColor =
        document.getElementById('detailVehicleColor');

    const detailStartsOn =
        document.getElementById('detailStartsOn');

    const detailEndsOn =
        document.getElementById('detailEndsOn');

    const detailPaymentStatus =
        document.getElementById('detailPaymentStatus');

    const detailAmount =
        document.getElementById('detailAmount');

    const detailReservationId =
        document.getElementById('detailReservationId');

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    const formatAdminSpaceDate = (value) => {

        if (!value) {
            return '—';
        }

        const parts = value.split('-');

        if (parts.length !== 3) {
            return value;
        }

        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    };

    const formatAdminSpaceMoney = (value) => {

        const amount =
            Number.parseFloat(value || '0');

        if (!Number.isFinite(amount)) {
            return 'S/ 0.00';
        }

        return `S/ ${amount.toFixed(2)}`;
    };

    const getSpaceStatusLabel = (status) => {

        switch (status) {

            case 'available':
                return 'Libre';

            case 'reserved':
                return 'Reservada';

            case 'occupied':
                return 'Ocupada';

            case 'maintenance':
                return 'Mantenimiento';

            default:
                return '—';
        }
    };

    const getSpaceStatusBadge = (status) => {

        switch (status) {

            case 'available':
                return 'text-bg-success';

            case 'reserved':
                return 'text-bg-warning';

            case 'occupied':
                return 'text-bg-danger';

            case 'maintenance':
                return 'text-bg-secondary';

            default:
                return 'text-bg-secondary';
        }
    };

    const getPaymentStatusLabel = (status) => {

        switch (status) {

            case 'paid':
                return 'Pagado';

            case 'pending':
                return 'Pendiente';

            case 'unpaid':
                return 'Sin pagar';

            case 'rejected':
                return 'Rechazado';

            default:
                return '—';
        }
    };

    const getPaymentBadge = (status) => {

        switch (status) {

            case 'paid':
                return 'text-bg-success';

            case 'pending':
                return 'text-bg-warning';

            case 'unpaid':
                return 'text-bg-dark';

            case 'rejected':
                return 'text-bg-danger';

            default:
                return 'text-bg-secondary';
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Ocultar todos los estados del panel
    |--------------------------------------------------------------------------
    */

    const hideAllSpacePanels = () => {

        spaceDetailEmpty
            ?.classList
            .add('d-none');

        spaceAvailableMessage
            ?.classList
            .add('d-none');

        spaceDetailContent
            ?.classList
            .add('d-none');

        spaceDetailFooter
            ?.classList
            .add('d-none');
    };

    /*
    |--------------------------------------------------------------------------
    | Clic en plaza
    |--------------------------------------------------------------------------
    */

    adminSpaces.forEach((space) => {

        space.addEventListener('click', () => {

            /*
            |--------------------------------------------------------------------------
            | Selección visual
            |--------------------------------------------------------------------------
            */

            adminSpaces.forEach((item) => {

                item.classList.remove(
                    'selected'
                );
            });

            space.classList.add(
                'selected'
            );

            /*
            |--------------------------------------------------------------------------
            | Datos
            |--------------------------------------------------------------------------
            */

            const spaceCode =
                space.dataset.spaceCode || '—';

            const spaceStatus =
                space.dataset.spaceStatus || '';

            const driverName =
                space.dataset.driverName || '';

            const driverEmail =
                space.dataset.driverEmail || '';

            const driverPhone =
                space.dataset.driverPhone || '';

            const vehiclePlate =
                space.dataset.vehiclePlate || '';

            const vehicleBrand =
                space.dataset.vehicleBrand || '';

            const vehicleModel =
                space.dataset.vehicleModel || '';

            const vehicleColor =
                space.dataset.vehicleColor || '';

            const startsOn =
                space.dataset.startsOn || '';

            const endsOn =
                space.dataset.endsOn || '';

            const paymentStatus =
                space.dataset.paymentStatus || '';

            const amount =
                space.dataset.amount || '';

            const reservationId =
                space.dataset.reservationId || '';

            /*
            |--------------------------------------------------------------------------
            | Cabecera
            |--------------------------------------------------------------------------
            */

            if (detailSpaceCode) {

                detailSpaceCode.textContent =
                    spaceCode;
            }

            if (detailSpaceStatus) {

                detailSpaceStatus.textContent =
                    getSpaceStatusLabel(
                        spaceStatus
                    );

                detailSpaceStatus.className =
                    `badge ${getSpaceStatusBadge(spaceStatus)}`;
            }

            /*
            |--------------------------------------------------------------------------
            | Ocultar estados anteriores
            |--------------------------------------------------------------------------
            */

            hideAllSpacePanels();

            /*
            |--------------------------------------------------------------------------
            | PLAZA LIBRE
            |--------------------------------------------------------------------------
            */
if (spaceStatus === 'available') {

    if (spaceAvailableMessage) {

        spaceAvailableMessage
            .classList
            .remove('d-none');

        const title =
            spaceAvailableMessage
                .querySelector('strong');

        const text =
            spaceAvailableMessage
                .querySelector('p');

        const icon =
            spaceAvailableMessage
                .querySelector('i');

        if (title) {
            title.textContent =
                'Plaza disponible';
        }

        if (text) {
            text.textContent =
                'Esta plaza se encuentra libre y disponible para una nueva reserva.';
        }

        if (icon) {
            icon.className =
                'bi bi-check-circle';
        }
    }

    return;
}
            /*
            |--------------------------------------------------------------------------
            | MANTENIMIENTO
            |--------------------------------------------------------------------------
            */

            if (spaceStatus === 'maintenance') {

                if (spaceAvailableMessage) {

                    spaceAvailableMessage
                        .classList
                        .remove('d-none');

                    const title =
                        spaceAvailableMessage
                            .querySelector('strong');

                    const text =
                        spaceAvailableMessage
                            .querySelector('p');

                    const icon =
                        spaceAvailableMessage
                            .querySelector('i');

                    if (title) {
                        title.textContent =
                            'Plaza en mantenimiento';
                    }

                    if (text) {
                        text.textContent =
                            'Esta plaza no se encuentra disponible temporalmente.';
                    }

                    if (icon) {
                        icon.className =
                            'bi bi-tools';
                    }
                }

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | RESERVADA / OCUPADA
            |--------------------------------------------------------------------------
            */

            spaceDetailContent
                ?.classList
                .remove('d-none');

            spaceDetailFooter
                ?.classList
                .remove('d-none');

            /*
            |--------------------------------------------------------------------------
            | Conductor
            |--------------------------------------------------------------------------
            */

            if (detailDriverName) {

                detailDriverName.textContent =
                    driverName || '—';
            }

            if (detailDriverEmail) {

                detailDriverEmail.textContent =
                    driverEmail || '—';
            }

            if (detailDriverPhone) {

                detailDriverPhone.textContent =
                    driverPhone || '—';
            }

            /*
            |--------------------------------------------------------------------------
            | Vehículo
            |--------------------------------------------------------------------------
            */

            if (detailVehiclePlate) {

                detailVehiclePlate.textContent =
                    vehiclePlate || '—';
            }

            if (detailVehicleModel) {

                const vehicleDescription =
                    [
                        vehicleBrand,
                        vehicleModel,
                    ]
                        .filter(Boolean)
                        .join(' ');

                detailVehicleModel.textContent =
                    vehicleDescription || '—';
            }

            if (detailVehicleColor) {

                detailVehicleColor.textContent =
                    vehicleColor
                        ? `Color: ${vehicleColor}`
                        : '—';
            }

            /*
            |--------------------------------------------------------------------------
            | Vigencia
            |--------------------------------------------------------------------------
            */

            if (detailStartsOn) {

                detailStartsOn.textContent =
                    formatAdminSpaceDate(
                        startsOn
                    );
            }

            if (detailEndsOn) {

                detailEndsOn.textContent =
                    formatAdminSpaceDate(
                        endsOn
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Pago
            |--------------------------------------------------------------------------
            */

            if (detailPaymentStatus) {

                detailPaymentStatus.textContent =
                    getPaymentStatusLabel(
                        paymentStatus
                    );

                detailPaymentStatus.className =
                    `badge ${getPaymentBadge(paymentStatus)}`;
            }

            if (detailAmount) {

                detailAmount.textContent =
                    reservationId
                        ? formatAdminSpaceMoney(
                            amount
                        )
                        : '—';
            }
/*
|--------------------------------------------------------------------------
| Reserva
|--------------------------------------------------------------------------
*/

if (detailReservationId) {
    detailReservationId.textContent =
        reservationId
            ? `#${reservationId}`
            : '—';
}

        });
    });
}

/*
|--------------------------------------------------------------------------
| Cierre de DOMContentLoaded
|--------------------------------------------------------------------------
*/

});
/* HMI de cámaras: navegación y reproductores encapsulados. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-camera-module]').forEach((root) => {
        const find = (selector) => root.querySelector(selector);
        const cameras = JSON.parse(find('[data-camera-data]').textContent);
        const parking = find('[data-camera-parking]');
        const form = find('[data-camera-form]');
        const deletion = find('[data-camera-delete]');
        const players = new Map();
        let selected = null;
        let currentView = 'grid';
        let returnView = 'grid';
        let globalMuted = true;
        let zoom = 1;
        const message = (text) => { find('[data-camera-message]').textContent = text; };
        const element = (tag, className, text) => {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        };
        const button = (text, callback) => {
            const node = element('button', '', text);
            node.type = 'button';
            node.addEventListener('click', callback);
            return node;
        };
        const list = () => cameras.filter((camera) => Number(camera.parking_id) === Number(parking.value));
        const cameraAt = (channel) => list().find((camera) => Number(camera.channel_number) === channel);
        const firstCamera = () => list().find((camera) => camera.channel_number);
        const safeEndpoint = (value) => {
            if (!value) return false;
            try {
                const url = new URL(value);
                return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password;
            } catch { return false; }
        };
        const destroyPlayers = () => {
            players.forEach((player) => {
                clearTimeout(player.timer);
                player.hls?.destroy();
                player.video.pause();
                player.video.removeAttribute('src');
                player.video.load();
            });
            players.clear();
        };
        const diagnostic = (container, camera) => {
            container.replaceChildren();
            const player = camera && players.get(Number(camera.id));
            const resolution = player?.video.videoWidth ? player.video.videoWidth + ' × ' + player.video.videoHeight : 'No disponible';
            const rows = {
                'Estado de cámara': camera ? (camera.status === 'active' ? 'Activa' : 'Inactiva') : 'No configurada',
                'Protocolo': camera?.stream_protocol?.toUpperCase() || 'No disponible',
                'Calidad del endpoint': camera ? (camera.preferred_quality === 'main' ? 'Main-stream' : 'Sub-stream') : 'No disponible',
                'Resolución recibida': resolution,
                'Bitrate': 'Sin telemetría',
                'Latencia': 'Sin telemetría',
                'Reproductor': player?.state || (camera?.stream_url ? 'Sin iniciar' : 'Pendiente de transcodificador')
            };
            Object.entries(rows).forEach(([label, value]) => container.append(element('dt', '', label), element('dd', '', value)));
        };
        const refreshDiagnostics = () => {
            diagnostic(find('[data-camera-single-diagnostic]'), selected);
            diagnostic(find('[data-camera-settings-diagnostic]'), selected);
        };
        const startPlayer = (camera, video, overlay, badge) => {
            const player = { video, hls: null, timer: null, state: 'Sin iniciar' };
            players.set(Number(camera.id), player);
            const setState = (state, kind = '') => {
                player.state = state;
                badge.textContent = state;
                badge.dataset.state = kind;
                overlay.lastChild.textContent = state;
                overlay.hidden = kind === 'online';
                refreshDiagnostics();
            };
            const noSignal = () => { clearTimeout(player.timer); setState('Canal sin señal', 'error'); };
            video.addEventListener('playing', () => { clearTimeout(player.timer); setState('En línea', 'online'); });
            video.addEventListener('pause', () => { if (video.readyState >= 2) setState('Pausada'); });
            video.addEventListener('error', noSignal);
            video.addEventListener('loadedmetadata', refreshDiagnostics);
            video.addEventListener('waiting', () => {
                setState('Esperando señal');
                clearTimeout(player.timer);
                player.timer = setTimeout(noSignal, 15000);
            });
            const play = () => video.play().catch(() => {
                if (player.state !== 'Canal sin señal') setState('Pulsa Play para reproducir');
            });
            player.play = play;
            if (camera.status !== 'active') { setState('Inactiva'); return; }
            if (!safeEndpoint(camera.stream_url)) { setState('Stream no configurado'); return; }
            if (camera.stream_protocol === 'webrtc') { setState('WebRTC: requiere endpoint compatible y signaling'); return; }
            if (location.protocol === 'https:' && new URL(camera.stream_url).protocol === 'http:') {
                setState('Endpoint HTTP bloqueado en página HTTPS', 'error'); return;
            }
            setState('Conectando');
            player.timer = setTimeout(noSignal, 15000);
            try {
                if (window.Hls?.isSupported()) {
                    player.hls = new window.Hls({ enableWorker: true });
                    player.hls.on(window.Hls.Events.MANIFEST_PARSED, play);
                    player.hls.on(window.Hls.Events.ERROR, (_event, data) => {
                        if (data.fatal) {
                            noSignal();
                            player.hls.destroy();
                            player.hls = null;
                        }
                    });
                    player.hls.loadSource(camera.stream_url);
                    player.hls.attachMedia(video);
                } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    video.src = camera.stream_url;
                    play();
                } else {
                    clearTimeout(player.timer);
                    setState('HLS no disponible en este navegador', 'error');
                }
            } catch { noSignal(); }
        };
        const card = (channel, camera, single = false) => {
            const article = element('article', 'camera-channel');
            const stage = element('div', 'camera-channel-stage');
            const overlay = element('div', 'camera-channel-overlay');
            overlay.append(element('i', 'bi bi-camera-video-off'), element('span', '', 'No configurado'));
            const top = element('div', 'camera-channel-top');
            const badge = element('span', 'camera-channel-badge', 'No configurado');
            top.append(element('span', 'camera-channel-tag', 'CANAL ' + channel), badge);
            const bottom = element('div', 'camera-channel-bottom');
            const title = element('div');
            title.append(element('strong', '', camera?.name || 'Canal ' + channel), element('small', '', camera?.location || camera?.coverage || 'No configurado'));
            const controls = element('div', 'camera-channel-controls');
            if (camera) {
                const video = element('video');
                video.autoplay = true;
                video.muted = globalMuted;
                video.playsInline = true;
                video.crossOrigin = 'anonymous';
                video.style.filter = 'brightness(' + camera.brightness + '%) contrast(' + camera.contrast + '%) saturate(' + camera.saturation + '%)';
                stage.append(video);
                const osd = element('div', 'camera-channel-osd');
                osd.dataset.cameraOsd = String(camera.id);
                stage.append(osd);
                controls.append(button(single ? 'Play / Pause' : 'Ampliar', () => single ? togglePlay() : openSingle(camera)));
                controls.append(button('Configurar', () => openSettings(camera)));
                if (!single) {
                    stage.tabIndex = 0;
                    stage.setAttribute('role', 'button');
                    stage.setAttribute('aria-label', 'Ampliar ' + camera.name);
                    stage.addEventListener('click', () => openSingle(camera));
                    stage.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openSingle(camera); }
                    });
                }
                // Start after DOM insertion; events and data stay local to this root.
                queueMicrotask(() => { if (article.isConnected) startPlayer(camera, video, overlay, badge); });
            } else {
                controls.append(button('Configurar canal', () => openSettings(null, channel)));
            }
            stage.append(overlay, top);
            bottom.append(title, controls);
            article.append(stage, bottom);
            return article;
        };
        const show = (view) => {
            currentView = view;
            root.querySelectorAll('[data-camera-view]').forEach((node) => { node.hidden = node.dataset.cameraView !== view; });
            root.querySelectorAll('[data-camera-action="grid"],[data-camera-action="single"],[data-camera-action="manage"]').forEach((node) => {
                node.setAttribute('aria-pressed', String(node.dataset.cameraAction === view));
            });
        };
        const renderGrid = () => {
            destroyPlayers();
            show('grid');
            const grid = find('[data-camera-grid]');
            grid.replaceChildren();
            for (let channel = 1; channel <= 4; channel++) grid.append(card(channel, cameraAt(channel)));
            message('');
        };
        const openSingle = (camera) => {
            if (!camera) { message('Selecciona un canal configurado.'); return; }
            destroyPlayers();
            selected = camera;
            zoom = 1;
            find('[data-camera-action="zoom"]').textContent = 'Zoom: 1×';
            find('[data-camera-single]').replaceChildren(card(Number(camera.channel_number), camera, true));
            find('[data-camera-single-name]').textContent = camera.name;
            find('[data-camera-single-coverage]').textContent = [camera.location, camera.coverage].filter(Boolean).join(' · ');
            find('[data-camera-quality]').value = camera.preferred_quality;
            show('single');
            refreshDiagnostics();
            message('');
        };
        const tab = (name) => {
            root.querySelectorAll('[data-camera-panel]').forEach((node) => { node.hidden = node.dataset.cameraPanel !== name; });
            root.querySelectorAll('[data-camera-tab]').forEach((node) => { node.setAttribute('aria-pressed', String(node.dataset.cameraTab === name)); });
        };
        const defaults = (channel) => ({
            parking_id: parking.value, name: '', channel_number: channel, location: '', coverage: '', status: 'active',
            rtsp_path: '/Streaming/Channels/' + channel + '02', stream_url: '', stream_protocol: 'hls',
            preferred_quality: 'sub', brightness: 100, contrast: 100, saturation: 100, osd_datetime: 1, osd_camera_name: 1
        });
        const openSettings = (camera, channel = 1) => {
            const previousPreview = find('[data-camera-test-preview]');
            if (previousPreview) { destroyPlayers(); previousPreview.remove(); }
            returnView = currentView === 'single' ? 'single' : (currentView === 'manage' ? 'manage' : 'grid');
            selected = camera;
            const data = camera || defaults(channel);
            form.reset();
            Object.entries(data).forEach(([key, value]) => {
                const input = form.elements.namedItem(key);
                if (!input) return;
                if (input.type === 'checkbox') input.checked = Number(value) === 1;
                else input.value = value ?? '';
            });
            form.elements.action.value = camera ? 'update' : 'create';
            form.elements.camera_id.value = camera?.id || '';
            deletion.hidden = !camera;
            deletion.elements.camera_id.value = camera?.id || '';
            find('[data-camera-settings-title]').textContent = camera ? 'Configuración · ' + camera.name : 'Configurar canal ' + channel;
            ['brightness', 'contrast', 'saturation'].forEach((key) => { find('[data-camera-output="' + key + '"]').textContent = data[key] + '%'; });
            tab('device');
            show('settings');
            refreshDiagnostics();
            message('');
        };
        const manage = () => {
            destroyPlayers();
            const container = find('[data-camera-management]');
            container.replaceChildren();
            list().forEach((camera) => {
                const row = element('div', 'camera-management-row');
                row.append(element('span', '', camera.name + ' · ' + (camera.channel_number ? 'Canal ' + camera.channel_number : 'Sin canal asignado')), button('Configurar', () => openSettings(camera)));
                container.append(row);
            });
            if (!list().length) container.append(element('p', '', 'No hay cámaras registradas.'));
            show('manage');
        };
        const togglePlay = () => {
            const player = selected && players.get(Number(selected.id));
            if (!player) return;
            if (player.video.paused) player.play?.();
            else player.video.pause();
        };
        const fullscreen = async (node) => {
            try {
                if (document.fullscreenElement) await document.exitFullscreen();
                else if (node?.requestFullscreen) await node.requestFullscreen();
                else message('Pantalla completa no disponible.');
            } catch { message('No se pudo abrir pantalla completa.'); }
        };
        const snapshot = () => {
            const video = selected && players.get(Number(selected.id))?.video;
            if (!video?.videoWidth || video.readyState < 2) { message('No hay un fotograma disponible para capturar.'); return; }
            try {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const context = canvas.getContext('2d');
                context.filter = video.style.filter;
                context.drawImage(video, 0, 0);
                canvas.toBlob((blob) => {
                    if (!blob) { message('No se pudo generar la captura.'); return; }
                    const url = URL.createObjectURL(blob);
                    const link = element('a');
                    link.href = url;
                    link.download = 'canal-' + selected.channel_number + '-' + Date.now() + '.png';
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                    message('Captura generada; descarga solicitada al navegador.');
                }, 'image/png');
            } catch { message('No se pudo capturar el vídeo. Verifica los permisos CORS del endpoint.'); }
        };
        root.addEventListener('click', (event) => {
            const target = event.target.closest('[data-camera-action],[data-camera-tab]');
            if (!target || !root.contains(target)) return;
            if (target.dataset.cameraTab) { tab(target.dataset.cameraTab); return; }
            const action = target.dataset.cameraAction;
            if (action === 'grid') renderGrid();
            else if (action === 'single') openSingle(selected && Number(selected.parking_id) === Number(parking.value) ? selected : firstCamera());
            else if (action === 'manage') manage();
            else if (action === 'create') {
                const channel = [1, 2, 3, 4].find((number) => !cameraAt(number));
                if (channel) openSettings(null, channel);
                else message('Los cuatro canales están configurados en este estacionamiento.');
            }
            else if (action === 'settings') openSettings(selected);
            else if (action === 'back') {
                if (returnView === 'single' && selected) openSingle(selected);
                else if (returnView === 'manage') manage();
                else renderGrid();
            }
            else if (action === 'play') togglePlay();
            else if (action === 'mute') {
                const video = selected && players.get(Number(selected.id))?.video;
                if (video) { video.muted = !video.muted; message(video.muted ? 'Audio silenciado.' : 'Audio activado si el stream lo incluye.'); }
            }
            else if (action === 'audio') {
                globalMuted = !globalMuted;
                players.forEach((player) => { player.video.muted = globalMuted; });
                target.textContent = globalMuted ? 'Audio global: silenciado' : 'Audio global: activado';
                target.setAttribute('aria-pressed', String(!globalMuted));
            }
            else if (action === 'fullscreen') fullscreen(root);
            else if (action === 'video-fullscreen') fullscreen(find('[data-camera-single] .camera-channel'));
            else if (action === 'snapshot') snapshot();
            else if (action === 'zoom') {
                const video = selected && players.get(Number(selected.id))?.video;
                zoom = zoom >= 3 ? 1 : zoom + 0.5;
                if (video) video.style.transform = 'scale(' + zoom + ')';
                target.textContent = 'Zoom: ' + zoom + '×';
            }
            else if (action === 'test' || action === 'restart') {
                if (!selected) { message('Guarda la cámara antes de probar el stream.'); return; }
                destroyPlayers();
                const preview = card(Number(selected.channel_number), selected, true);
                find('[data-camera-single]').replaceChildren(preview);
                // A visible preview in diagnostics also works when opened from Gestión.
                let holder = find('[data-camera-test-preview]');
                if (!holder) {
                    holder = element('div');
                    holder.dataset.cameraTestPreview = '';
                    find('[data-camera-panel="diagnostic"]').append(holder);
                }
                holder.replaceChildren(preview);
                message('Prueba del endpoint guardado iniciada en el navegador.');
            }
        });
        parking.addEventListener('change', () => { selected = null; renderGrid(); });
        form.addEventListener('input', (event) => {
            if (['brightness', 'contrast', 'saturation'].includes(event.target.name)) {
                find('[data-camera-output="' + event.target.name + '"]').textContent = event.target.value + '%';
            }
        });
        const updatePath = () => {
            form.elements.rtsp_path.value = '/Streaming/Channels/' + form.elements.channel_number.value + (form.elements.preferred_quality.value === 'main' ? '01' : '02');
        };
        form.elements.channel_number.addEventListener('change', updatePath);
        form.elements.preferred_quality.addEventListener('change', updatePath);
        form.addEventListener('invalid', (event) => {
            const panel = event.target.closest('[data-camera-panel]');
            if (panel) tab(panel.dataset.cameraPanel);
        }, true);
        deletion.addEventListener('submit', (event) => {
            if (!window.confirm('¿Eliminar esta cámara? El canal quedará sin configurar.')) event.preventDefault();
        });
        find('[data-camera-quality]').addEventListener('change', (event) => {
            if (!selected) return;
            if (event.target.value !== selected.preferred_quality) {
                message('Configura y guarda el endpoint web de esa calidad en Red y streaming.');
                event.target.value = selected.preferred_quality;
            }
        });
        const clockTimer = setInterval(() => {
            root.querySelectorAll('[data-camera-osd]').forEach((node) => {
                const camera = cameras.find((item) => Number(item.id) === Number(node.dataset.cameraOsd));
                node.textContent = [
                    Number(camera?.osd_camera_name) ? camera.name : '',
                    Number(camera?.osd_datetime) ? new Date().toLocaleString('es-PE') + ' · Hora del visor' : ''
                ].filter(Boolean).join(' · ');
                node.hidden = !node.textContent;
            });
        }, 1000);
        window.addEventListener('pagehide', () => { clearInterval(clockTimer); destroyPlayers(); }, { once: true });
        renderGrid();
    });
});

/* Account settings and the shared notification center. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-settings-module]').forEach((root) => {
        const buttons = Array.from(root.querySelectorAll('[data-settings-tab]'));
        const show = (name, focus = false) => {
            buttons.forEach((button) => {
                const selected = button.dataset.settingsTab === name;
                button.setAttribute('aria-selected', String(selected));
                button.tabIndex = selected ? 0 : -1;
                if (selected && focus) button.focus();
            });
            root.querySelectorAll('[data-settings-panel]').forEach((panel) => { panel.hidden = panel.dataset.settingsPanel !== name; });
        };
        buttons.forEach((button, index) => {
            button.addEventListener('click', () => show(button.dataset.settingsTab));
            button.addEventListener('keydown', (event) => {
                let target;
                if (['ArrowRight', 'ArrowDown'].includes(event.key)) target = (index + 1) % buttons.length;
                if (['ArrowLeft', 'ArrowUp'].includes(event.key)) target = (index + buttons.length - 1) % buttons.length;
                if (event.key === 'Home') target = 0;
                if (event.key === 'End') target = buttons.length - 1;
                if (target !== undefined) { event.preventDefault(); show(buttons[target].dataset.settingsTab, true); }
            });
        });
        root.querySelectorAll('[data-settings-phone]').forEach((input) => input.addEventListener('input', () => {
            input.value = input.value.replace(/[^0-9]/g, '').slice(0, 9);
        }));
    });
    document.querySelectorAll('[data-notification-center]').forEach((root) => {
        const toggle = root.querySelector('[data-notification-toggle]');
        const panel = root.querySelector('[data-notification-panel]');
        const list = root.querySelector('[data-notification-list]');
        const dot = root.querySelector('[data-notification-dot]');
        const feedback = root.querySelector('[data-notification-feedback]');
        const markAll = root.querySelector('[data-notification-mark-all]');
        const clearRead = root.querySelector('[data-notification-clear-read]');
        let loading = false;
        let unreadCount = 0;
        let readCount = 0;
        const updateActions = () => {
            markAll.disabled = loading || unreadCount === 0;
            clearRead.disabled = loading || readCount === 0;
        };
        const node = (tag, text, className = '') => {
            const item = document.createElement(tag);
            item.textContent = text;
            item.className = className;
            return item;
        };
        const safeUrl = (path) => {
            try {
                const url = new URL(path, location.origin);
                return url.origin === location.origin && url.pathname.startsWith('/devioz-parking-php/frontend/') ? url.href : null;
            } catch { return null; }
        };
        const render = (data) => {
            list.replaceChildren();
            dot.hidden = Number(data.unread_count) === 0;
            unreadCount = Number(data.unread_count);
            readCount = Number(data.read_count);
            updateActions();
            if (!data.items.length) list.append(node('p', 'No tienes notificaciones.', 'notification-empty'));
            data.items.forEach((notice) => {
                const item = node('article', '', 'notification-item');
                item.classList.toggle('is-unread', Number(notice.is_read) === 0);
                const icon = node('i', '', 'bi ' + ({ reservations: 'bi-calendar-check', payments: 'bi-credit-card', pending_actions: 'bi-clock-history', system_notices: 'bi-shield-check' }[notice.type] || 'bi-bell'));
                icon.setAttribute('aria-hidden', 'true');
                const content = node('div', '', 'notification-content');
                content.append(node('strong', notice.title), node('p', notice.message));
                const date = new Date(notice.created_at.replace(' ', 'T'));
                const time = node('time', Number.isNaN(date.getTime()) ? notice.created_at : date.toLocaleString('es-PE'));
                content.append(time);
                if (notice.action_url) {
                    const url = safeUrl(notice.action_url);
                    if (url) { const link = node('a', 'Ver detalle'); link.href = url; content.append(link); }
                }
                if (Number(notice.is_read) === 0) {
                    const mark = node('button', 'Marcar como leído');
                    mark.type = 'button';
                    mark.addEventListener('click', () => request('mark_read', notice.id));
                    content.append(mark);
                }
                item.append(icon, content);
                list.append(item);
            });
        };
        const request = async (action, id) => {
            if (loading) return;
            loading = true;
            updateActions();
            feedback.textContent = action ? 'Guardando…' : 'Cargando…';
            try {
                const options = { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } };
                if (action) {
                    options.method = 'POST';
                    options.body = new URLSearchParams({ action, csrf_token: root.dataset.csrf });
                    if (id) options.body.set('notification_id', id);
                }
                const response = await fetch(root.dataset.endpoint, options);
                if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('response');
                const data = await response.json();
                render(data);
                feedback.textContent = action === 'clear_read'
                    ? `Se eliminaron ${Number(data.deleted_count)} notificaciones leídas. Las no leídas se conservan.`
                    : (action ? 'Estado actualizado.' : '');
            } catch { feedback.textContent = 'No se pudieron actualizar las notificaciones. Vuelve a abrir la campana para reintentar.'; }
            finally { loading = false; updateActions(); }
        };
        const close = (focus = false) => { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); if (focus) toggle.focus(); };
        toggle.addEventListener('click', () => {
            const open = panel.hidden;
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            if (open) request();
        });
        document.addEventListener('click', (event) => { if (!root.contains(event.target)) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !panel.hidden) close(true); });
        markAll.addEventListener('click', () => request('mark_all'));
        clearRead.addEventListener('click', () => request('clear_read'));
        request();
    });
});
