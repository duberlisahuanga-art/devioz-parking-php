# Auditoría: garantías de registro e identidad

Vehículos y registro de cuentas guardan el cambio y el evento en una transacción.
El login escribe su evento antes de establecer la sesión autenticada.

Los eventos nuevos incluyen en `metadata.actor` el ID, nombre, correo y rol al
momento de actuar. No se reconstruyen identidades históricas con perfiles actuales.
Los eventos anteriores sin esa información se muestran como identidad histórica
no disponible. La búsqueda de nombre/correo usa la identidad histórica.
No hay migración ni modificación retroactiva de datos.

Los fallos de credenciales, cuentas inactivas y los rechazos de los permisos
compartidos de administrador, conductor y propiedad de reserva producen eventos
con motivos controlados. No incluyen contraseña, token ni correo intentado.
Los intentos de login bloqueados por el limitador no generan eventos adicionales.
Los rechazos se insertan mediante una conexión independiente y sin FK al actor
(su identidad permanece en metadata), evitando bloqueo por cambios de usuario
y pérdida por rollback. También se registran rechazos específicos de vehículo,
reserva/renovación/cancelación, pago, comprobantes, CSRF y sesión inactiva.
Si falla SQL, se guarda JSONL privado en `backend/storage/audit-fallback`, con
aviso al log del servidor. Si también falla el disco, se emite una alerta crítica
al log; no se concede acceso. Los archivos de contingencia se respaldan y no se
importan automáticamente ni aparecen en la tabla de la pantalla de auditoría.

Validación: `php tools/quality.php` y `php tests/integration.php`.
La integración crea una base aleatoria `devioz_test_*` y la elimina al terminar.
Incluye identidad tras cambio/eliminación, búsqueda histórica, rechazo de permisos,
fallo de login sin secretos y reversión de vehículos/cuentas al fallar auditoría.

Segunda etapa: filtros por fecha inclusiva en Lima, módulo, acción, identidad
histórica y entidad, validados en servidor y conservados en paginación.
El detalle usa una lista permitida de campos: motivos, referencia y diferencias
de cámaras/egresos. No muestra metadatos arbitrarios, URLs ni rutas de archivos.
Las nuevas actualizaciones de cámaras guardan antes/después de campos permitidos.

El instalador local concede SELECT/INSERT sobre auditoría y CRUD por tabla para
el resto, excluyendo schema_migrations. Se reutiliza `AuditPermissions::grant`.
Al añadir tablas futuras deben concederse permisos explícitos con una cuenta de
migración. La aplicación no recibe permisos DDL ni privilegios globales.

Se ejecutó `tools/harden-audit-account.php --apply`: se creó y verificó una cuenta
nueva, se activó mediante `backend/config/local.php` y se conservó la cuenta
anterior. La verificación efectiva permite SELECT/INSERT y recibe error 1142 para
UPDATE/DELETE. La inserción de comprobación se revierte; no agrega eventos ficticios.
No se revocaron privilegios de cuentas compartidas como root.

Recuperación de configuración: el archivo anterior está en
`backend/storage/audit-recovery/local-20261015-201645-08b0187b.php`.
Para revertir, copiarlo sobre `backend/config/local.php` con el servicio detenido,
validar conexión y arrancar de nuevo. Mantener por separado los valores actuales
AUDIT_BACKUP_KEY y AUDIT_BACKUP_MIRROR_DIR: el respaldo anterior al cambio no los
contiene. El usuario SQL anterior se conserva; no se necesita destruir la cuenta
nueva para recuperar acceso. Ambos archivos contienen secretos y no deben publicarse.

## Respaldo y conservación operativa

El 15/10/2026 el usuario autorizó una limpieza puntual para conservar los cinco
días calendario del 11 al 15 de octubre en Lima. Se ejecutó
`php tools/prune-audit.php 2026-10-11 --apply`: 160 eventos eliminados, 239
restantes (incluido el evento de la limpieza), cero eventos anteriores al corte.
Antes de borrar se respaldaron y restauraron temporalmente los 398 eventos, y
se verificó la segunda copia en el destino autorizado del Escritorio.
Archivo: `audit-before-prune-20261015-204003-1f277cd2.jsonl` y su manifiesto.
La eliminación fue transaccional y no cambió permisos de la cuenta de aplicación.
La herramienta sólo opera por CLI con cuenta de mantenimiento; sin `--apply`
muestra una vista previa. No se programó borrado recurrente ni se borraron copias.
Integración posterior de esta herramienta: 57 comprobaciones aprobadas, incluido
el caso que conserva exactamente las 00:00 del primer día; calidad aprobada.

Política inicial: conservar todos los eventos, archivos de contingencia y copias;
sin purga ni sobreescritura automática. Una reducción de plazo o eliminación
requiere una decisión explícita del responsable del negocio. Revisar espacio
disponible y restringir la consulta a administradores. Esto no establece un plazo
legal de conservación ni certifica conformidad.

`tools/audit-backup.php` exporta una instantánea JSONL, un manifiesto con cantidad
y HMAC-SHA256, y copia los archivos de contingencia con su HMAC. El archivo principal
y manifiesto se replican a `C:\Users\Usuario\Desktop\devioz-parking-php`, destino
autorizado por el usuario. Se comprueba que ambas copias coincidan byte a byte.
El HMAC detecta modificaciones sin la clave, pero no cifra los datos. La clave vive
en configuración privada, no en la carpeta de copias; conservarla en un gestor de
secretos o medio separado antes de recuperar el equipo. No se imprimieron claves.

Se instaló la tarea `DEVIOZ-Parking-AuditBackup`, diaria a las 02:00 de Lima
(zona Windows comprobada: SA Pacific Standard Time), S4U con privilegios limitados.
La ejecución inicial terminó con resultado 0 y `mirror_verified=true`.
Si el equipo está apagado, se ejecuta cuando vuelva a estar disponible. No se
cambió la política global de ejecución de PowerShell.

Estado: `backend/storage/audit-backups/last-success.json` y
`Get-ScheduledTaskInfo -TaskName DEVIOZ-Parking-AuditBackup`. Revisar que la última
copia no tenga más de 36 horas y que LastTaskResult sea 0. Ante errores revisar
disco, acceso al destino y disponibilidad de MariaDB; ejecutar el respaldo manual
y comprobar otra vez el estado. La tarea devuelve un error si falla la segunda copia.

Respaldo manual: `C:\xampp\php\php.exe tools\audit-backup.php`.
Verificación de restauración: con una cuenta de mantenimiento que tenga CREATE
TEMPORARY TABLES, ejecutar `tools/audit-backup.php verify RUTA_JSONL`. Las credenciales
de mantenimiento se reciben en DB_MAINTENANCE_USERNAME/DB_MAINTENANCE_PASSWORD;
no se guardan en la tarea. La verificación restaura sólo a una tabla temporal,
compara cada campo y la elimina; no escribe sobre audit_logs. Se restauraron y
compararon 398 eventos reales desde la copia local y desde el Escritorio.
Para una recuperación real, restaurar primero en una base aislada, cotejar el
esquema y los archivos de contingencia, y aprobar por separado cualquier cambio
de la tabla operativa; esta herramienta deliberadamente no la reemplaza.

El Escritorio es una segunda ubicación en el mismo equipo: no protege ante pérdida
del disco, ransomware o acceso administrativo al sistema. Para cubrir esos casos
se requiere un medio distinto o copia fuera del equipo; no se conectaron terceros.
El snapshot preserva identidad frente a cambios de perfil, pero no impide
manipulación por administradores SQL/Windows con acceso a los datos y la clave.

## Evidencia de validación

- Calidad: lint de 93 archivos, 11 casos de ingreso, 7 pruebas PHPUnit/13 aserciones
  y PHPStan aprobados; permanecen advertencias de estilo preexistentes.
- Integración: 54 comprobaciones en una base aleatoria descartable, incluidos
  rollback, contingencia, permisos efectivos, restauración, copia y rechazo de
  un respaldo alterado.
- Auditoría en navegador: escritorio 1440×900 y móvil 390×844, temas claro/oscuro,
  teclado, filtros, respuesta 400 para fechas inválidas y reglas seleccionadas
  de axe sin violaciones. Capturas en test-results/audit-*.png.
- Regresión de `audit.spec.js` y `flows.spec.js`: 20 casos aprobados con fixture
  nuevo; incluye autenticación, registro, propietario de comprobantes, CSRF y
  notificaciones. La primera ejecución sobre el fixture reutilizado encontró
  13 avisos pendientes donde el caso esperaba 1. Al repetir con datos iniciales
  limpios pasó sin modificar expectativas ni código de notificaciones. La base
  nueva se eliminó al terminar y se restauró el archivo del fixture previo,
  conservando su base de datos. Log privado: backend/storage/audit-browser-regression.log.
- Node portátil oficial en tools/runtime, con SHA256 cotejado con manifiesto
  oficial; no se instaló globalmente ni se cambió el despliegue de la aplicación.
