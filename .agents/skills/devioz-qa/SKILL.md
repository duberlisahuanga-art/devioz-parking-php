---
name: devioz-qa
description: Verificar cambios y preparar entregas de DEVIOZ mediante pruebas de comportamiento, revisión de regresiones y evidencia de calidad.
---

# QA de DEVIOZ

Derivar la aceptación de la solicitud: acción, resultado esperado, rol y casos
negativos pertinentes. Seleccionar pruebas proporcionales al cambio; no crear
pruebas que sólo repitan la implementación o comprueben frases de documentación.

Ejecutar `php tools/quality.php` desde la raíz con las dependencias Composer
instaladas: lint, manifests, política de ingreso, PHPUnit, PHPStan y estilo.
Para cambios de datos ejecutar `php tests/integration.php` en la base generada.
Para flujos web crear fixture con `php tools/browser-fixture.php create`, ejecutar
`npm run test:e2e` y limpiarlo con `php tools/browser-fixture.php clean`.
Concurrencia y recuperación tienen pruebas en `tests/concurrency.php` y
`tests/restore.php`, usando ese fixture. Consultar `docs/hardening-2026-10-14.md`.
Un resultado verde no prueba todas las rutas ni certifica producción.

Para cambios de negocio agregar casos de comportamiento. Para reservas cubrir
fechas límite, falta de pago, cancelación y exclusión de plazas; para finanzas
montos aprobados/pendientes y límites de periodo. Para permisos incluir rechazo
por rol y propiedad. Pruebas de integración usan base separada, nunca la base
operativa local como fixture descartable.

Para cambios visuales verificar en navegador móvil/escritorio, teclado y temas.
Para cambios en archivos verificar carga válida, rechazo y acceso al comprobante.
Para preparación de entrega consultar `docs/development-quality.md`: separar
pruebas automatizadas, comprobaciones manuales y requisitos pendientes.

Si una prueba falla, identificar si es regresión o deuda previa con evidencia.
No ocultar fallos con baseline nuevo, saltos o cambios de expectativas sin una
razón de producto. Reportar comando, resultado y limitaciones sin datos sensibles.
