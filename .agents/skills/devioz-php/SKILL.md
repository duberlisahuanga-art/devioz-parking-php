---
name: devioz-php
description: Implementar o revisar lógica PHP del backend DEVIOZ PARKING, preservando sus reglas de reservas, pagos y permisos.
---

# PHP de DEVIOZ

Identificar el punto de entrada, controlador, modelo y servicio existentes.
`backend/bootstrap.php` carga el autoload Composer cuando está instalado y
mantiene compatibilidad con cargas manuales. `app_url()` usa APP_BASE_URL,
con `/devioz-parking-php` como valor local. No cambiarla sin revisar el despliegue.

Ubicar cada cambio en la capa existente: controladores para casos de uso,
modelos para persistencia y servicios para efectos transversales. Mantener
`declare(strict_types=1)` y contratos explícitos en código nuevo. Aplicar
PSR-12 al código modificado sin reformatear archivos ajenos al cambio.

Para reservas o pagos, revisar las transiciones, autorización y concurrencia,
incluidos reintentos y acciones repetidas. No convertir un pago pendiente en
aprobado por mostrar un comprobante. Mantener sincronizados reserva y plaza
dentro de una transacción cuando cambien conjuntamente.

Las fechas de negocio usan America/Lima y límites explícitos. Probar fin de
mes si se cambia `monthly_end_date()` y el último día incluido si se cambia
`ReservationController::entryBlockReason()`.

Reutilizar `require_auth()`, los permisos especializados y la validación CSRF
del punto de entrada. Las excepciones no deben revelar SQL ni credenciales al
usuario; conservar información diagnóstica en registros controlados.

Ejecutar desde la raíz `php tools/quality.php` y las pruebas de comportamiento
pertinentes al cambio. Explicar las dependencias y limitaciones reales.
