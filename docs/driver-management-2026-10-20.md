# Gestionar conductor desde Usuarios

## Uso

En **Administrador → Usuarios**, cada conductor activo tiene el botón
**Gestionar conductor**. Abre sus pantallas existentes de dashboard, vehículos,
reservas, nueva reserva y pagos Yape. Una cuenta inactiva debe activarse primero;
el servidor comprueba nuevamente su estado al atender cada operación.

El aviso **Gestionando a: nombre** muestra el conductor seleccionado y el
administrador que registra las operaciones. **Volver a administración** regresa
a Usuarios. El nombre, rol y sesión del administrador se conservan.

Se pueden registrar vehículos y reservas, renovar mensualidades, solicitar
cancelaciones y adjuntar comprobantes Yape usando sus validaciones existentes.
La cancelación y el comprobante conservan su revisión administrativa. Las
notificaciones y la configuración de cuenta siguen siendo las del administrador
autenticado; no se obtiene la contraseña ni se inicia sesión como el conductor.

El efectivo no se incorpora en esta entrega.

## Funcionamiento y permisos

- `DriverContext` separa al administrador autenticado del conductor propietario.
  `current_user()`, `is_admin()` y la sesión no se sustituyen.
- La selección explícita `managed_driver_id` se valida en servidor: administrador
  activo y conductor existente, activo y con rol conductor. No concede acceso
  a invitados ni a otros conductores.
- La selección viaja en enlaces, filtros, paginación y redirecciones posteriores
  a POST. No se guarda una selección global en la sesión; dos pestañas pueden
  gestionar conductores diferentes.
- Los controladores reutilizan propiedad de vehículos/reservas, disponibilidad,
  transacciones, CSRF, validación de archivos y estados de pago existentes.
- La auditoría conserva al administrador como autor y añade `managed_driver`
  con la identidad del propietario de la operación. Los cierres automáticos de
  mensualidades mantienen su marca de mantenimiento global.
- No se necesitan migraciones ni cambios de credenciales o datos operativos.

## Verificación

Resultados del 20/10/2026:

- `php tests/driver_management.php`: 26 comprobaciones en una base temporal,
  eliminada al terminar. Incluye identidad, selección inválida, cuenta inactiva,
  propiedad, auditoría, reserva, cancelación, efectivo rechazado y regresión del
  conductor que usa su propia cuenta.
- `php tests/integration.php`: 110 comprobaciones correctas en otra base temporal.
- Chromium, escritorio y móvil: 12 pruebas correctas de gestión, Usuarios y
  regresiones de portada. Incluye alta de vehículo, reserva y carga real de un
  PDF de prueba por HTTP; CSRF inválido, navegación, pestañas independientes y
  retorno a administración.
- Se repitieron los dos casos de carga Yape para comprobar además la fila de
  historial en estado pendiente y la descarga efectiva del PDF almacenado.
- Capturas revisadas de escritorio y móvil, ambos temas ejercitados; comprobación
  específica de contraste y nombre del enlace en el aviso de gestión con axe.
- `php tools/quality.php`: código 0. Lint, PHPUnit, PHPStan y estilo, con las
  advertencias de estilo existentes. El nuevo contexto también pasó PHPCS.

Los fixtures de navegador usan exclusivamente bases `devioz_browser_*`. Para
esta ejecución se conservó el fixture anterior, se creó uno nuevo y se limpió
únicamente el nuevo antes de restablecer la referencia al anterior. La corrección
del slug del estacionamiento en el generador de fixtures permite probar la ruta
real de creación de reservas. No cambia el estacionamiento operativo.

Comandos principales, con PHP y Node disponibles en PATH:

```powershell
php tests/driver_management.php
php tests/integration.php
node node_modules/@playwright/test/cli.js test tests/browser/driver-management.spec.js tests/browser/users-status.spec.js tests/browser/flows.spec.js --grep 'panel real|comprobante Yape|selección administrativa|usuarios: permisos|landing disponible|landing, etiquetas'
php tools/quality.php
```

Preparar primero un fixture sintético con `php tools/browser-fixture.php create`
y limpiarlo al terminar con `php tools/browser-fixture.php clean`. Si ya hay un
fixture en uso, conservarlo y aislar la ejecución antes de crear uno nuevo. Las
capturas, registros, configuración privada y respaldos permanecen fuera de Git.
