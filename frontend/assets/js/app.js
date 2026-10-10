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