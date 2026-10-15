# Mensualidades: vencimiento automático, avisos y renovación

Implementación del 15 de octubre de 2026. Las fechas usan America/Lima y el día
final del contrato se incluye completo. Una mensualidad con fin 14/11 puede
usarse hasta ese día; el vencimiento se procesa desde el 15/11.

## Comportamiento

- Si el vehículo está fuera (`confirmed`) y la fecha final pasó, el proceso marca
  la reserva `expired`. Libera la plaza si aún está reservada y no hay otra
  reserva confirmada/activa que la utilice. Nunca elimina la reserva ni el pago.
- Si está dentro (`active`), conserva reserva activa y plaza ocupada. La interfaz
  muestra «Mensualidad vencida · Pendiente de salida». Al registrar su salida,
  el flujo existente cierra la mensualidad y libera la plaza.
- Los ingresos siguen comprobando en servidor pago aprobado, periodo vigente,
  reserva confirmada y ausencia de cancelación pendiente. Un panel desactualizado
  no permite saltarse estas reglas.
- Los pagos anteriores conservan su estado y sus importes. No hay reembolsos,
  cobros por exceso ni aprobación automática de comprobantes.
- Una solicitud de cancelación pendiente se conserva para revisión. Si se revisa
  después del cierre automático, no cambia el estado final ni libera otra vez
  una plaza que pudiera haberse reasignado.

El proceso manda avisos internos al conductor y a administradores al entrar en
los tres últimos días, al cerrar la mensualidad y si vence con vehículo dentro.
Respeta las preferencias de notificación existentes. Los eventos tienen claves
únicas: ejecutarlo cada minuto no duplica los avisos. No se envían emails, SMS
ni mensajes externos.

## Ejecución independiente del navegador

Comando CLI (las peticiones HTTP a `tools/` siguen bloqueadas):

```powershell
& C:\xampp\php\php.exe tools\reservation-lifecycle.php
```

El proceso usa un bloqueo de MariaDB por base para evitar barridos simultáneos.
Cada cierre tiene una transacción con relectura bloqueada de la reserva/plaza,
comprobación de otras asignaciones, auditoría y aviso de cierre. Si falla la
auditoría o el aviso, se revierte ese cierre; la próxima ejecución reintenta.
Se procesan hasta 500 cierres por ejecución para limitar cada ciclo.

Como recuperación adicional, al abrir Reservas, consultar el croquis o crear una
reserva se ejecuta el mismo proceso idempotente. Esto no sustituye la tarea
programada si se necesita procesamiento cuando no hay usuarios conectados.

El instalador `tools/install-reservation-task.ps1` crea la tarea de Windows
`DEVIOZ-Parking-ReservationLifecycle`, cada minuto, con `php.exe` directamente,
sin ventanas ni contraseñas guardadas en el código. Usa el usuario actual con
inicio S4U y nivel limitado; debe registrarse desde una consola con los permisos
de Windows necesarios. S4U permite ejecutarla sin tener abierto el navegador ni
una sesión interactiva del usuario.

Instalación desde PowerShell como administrador, limitada a este proceso:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\devioz-parking-php\tools\install-reservation-task.ps1
```

El instalador no reemplaza una tarea ya existente. El ajuste de ExecutionPolicy
del comando es solo para esa ejecución; no modifica la política global. La tarea
ejecuta PHP y no depende de la política de archivos PowerShell.

Comprobación:

```powershell
Get-ScheduledTaskInfo -TaskName DEVIOZ-Parking-ReservationLifecycle
Get-Content C:\xampp\htdocs\devioz-parking-php\backend\storage\reservation-lifecycle.log -Tail 5
```

La tarea registra el resultado de cada ciclo en el almacenamiento privado de la
aplicación. En un servidor Linux, ejecutar el mismo CLI con cron cada minuto.
El servidor, MariaDB y su programador deben estar encendidos y disponibles.
Si estuvieron apagados, el siguiente ciclo procesa los vencimientos pendientes.

## Paneles y renovación

Administrador → Reservas separa «En curso» e «Historial de mensualidades».
Las finalizadas no desaparecen de la base: pasan al historial. Reservas y Croquis
consultan datos nuevos cada 30 segundos sin navegar ni recargar el documento.
El refresco conserva listeners y selección del croquis, y se pausa al editar un
formulario, enviar una acción o abrir un modal. Una pestaña oculta retoma la
actualización al volver a estar visible. Si falla la conexión, se informa y se
reintenta; el servidor continúa aplicando sus validaciones.

Conductor → Mis reservas → Historial y renovación permite «Renovar mensualidad»
una vez finalizado el periodo y registrada la salida. Se comprueban en servidor
propietario, vehículo, disponibilidad y ausencia de otras reservas de la plaza.
La renovación crea otra reserva con la misma plaza/vehículo, desde el día actual
y con precio mensual vigente. Se mantiene el registro anterior como historial.
El pago nuevo empieza sin pagar y debe ser aprobado para volver a ingresar.
Si la plaza ya se asignó, se indica elegir otra desde Reservar plaza.

La renovación anticipada con garantía de la misma plaza no forma parte de este
flujo: requeriría gestionar periodos futuros y transferencia de ocupación entre
mensualidades. La interfaz lo aclara: el botón se ofrece sobre mensualidades
finalizadas. Un índice único sobre `renewed_from_id` y el bloqueo de la reserva
anterior impiden duplicar renovaciones por dobles clics o solicitudes concurrentes.

## Migración, respaldo y recuperación

`database/migrations/20261015_reservation_renewals.sql` agrega una referencia
nullable `renewed_from_id`, su FK/índice único y un índice de estado/fecha final.
No modifica valores históricos. Se aplica con el ejecutor existente
`php tools/migrate.php` y queda registrada con checksum.

Antes de aplicarla localmente se guardó un respaldo SQL de la base en
`backend/storage/before-reservation-lifecycle-*.sql`. Estos archivos no son
públicos. MariaDB hace commit implícito del DDL; no se promete rollback del ALTER.
Para detener el procesamiento, deshabilitar la tarea y retirar las llamadas de
recuperación de `ReservationController` y `AdminController`. Desactivar solo el
JavaScript detiene el refresco visual, pero no la tarea del servidor.
No restaurar un respaldo sobre datos nuevos sin revisar antes sus diferencias.

## Verificación

- `php tests/reservation_lifecycle.php`: cierre de vencidas, permanencia de
  ocupación, último día inclusivo, pagos intactos, notificaciones idempotentes,
  salida después del vencimiento, historial, propiedad, nuevo pago, doble envío,
  revisión tardía, rollback y concurrencia de cierre/renovación en base aleatoria
  `devioz_lifecycle_test_*`, eliminada al finalizar.
- Playwright `tests/browser/reservation-lifecycle.spec.js`: reservas y croquis
  sin recarga, historial y renovación pendiente de pago en escritorio/móvil,
  usando exclusivamente `devioz_browser_*`.
- Los fixtures aplican la migración nueva; no se usan datos operativos como
  fixtures descartables. Ejecutar además `php tools/quality.php` e integración.

Resultados de esta entrega: 21 comprobaciones de ciclo de vida (incluye dos
procesos simultáneos), 26 comprobaciones de integración y 42 de catálogo,
correctas. Suite completa de Playwright: 26 pruebas correctas en escritorio y
móvil. `tools/quality.php`, PHPStan y `git diff --check` correctos; siguen visibles
las advertencias de estilo heredadas. Se revisaron las capturas de croquis en
ambos tamaños y el panel conservó el detalle de la plaza tras actualizarse.

No se añadieron frameworks ni dependencias de ejecución. La instalación de la
tarea es un requisito del sistema operativo adicional al código PHP.

Los proyectos Playwright de escritorio y móvil usan ámbitos de almacenamiento
separados en `tests/router.php`: compartir la misma IP/cuenta entre toda la suite
agotaba los contadores de login y producía respuestas 429. La lógica y los
umbrales de `RateLimiter` y `AuthController` no se modificaron. Esta separación
solo existe en el router del servidor de pruebas; Apache bloquea ese directorio.
