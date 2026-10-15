# Correcciones del escaneo de DEVIOZ

Fecha: 14 de octubre de 2026. Se conserva el informe original como evidencia
del estado anterior; este documento registra las correcciones aplicadas.

## Estado por hallazgo

| Hallazgo | Acción aplicada | Verificación / límite |
| --- | --- | --- |
| 01 Archivos internos públicos | Reglas Apache para Git, SQL, configuración, herramientas y artefactos privados; directorios sin listado | Peticiones reales a Apache: 403 |
| 02 Documentos públicos | Acceso estático bloqueado; endpoint por tipo/ID con rol, propiedad, MIME y ruta verificados | Anónimo, propietario, conductor ajeno y admin cubiertos por pruebas |
| 03 Concurrencia | Bloqueo de reserva antes de pagos/cancelación; actualización de pago condicionada; reintento de decisión idempotente; índice único de pagos activos | Dos procesos independientes: decisiones consistentes y una sola auditoría |
| 04 Producción | Usuario SQL local limitado; secretos privados; variables de entorno; rechazo de configuración insegura en producción; HTTPS/Secure/HSTS; demos retiradas | Configuración local aplicada. Dominio, certificado y servidor real aún requieren configuración y validación |
| 05 Búsquedas PDO | Placeholders únicos para usuarios/pagos/auditoría | Integración y navegador |
| 06 Fechas | America/Lima en aplicación y -05:00 en sesión SQL | Límites de día y mensualidad comprobados |
| 07 Contraseñas | Política común de 8–72 bytes y rechazo de NUL | PHPUnit y registro por HTTP |
| 08 Intentos de acceso | Límites por IP y combinación IP/cuenta, con bloqueo de archivo, claves HMAC y respuesta 429 | Prueba aislada del contador; revisar umbrales con tráfico real |
| 09 Errores públicos | Excepciones explícitas de validación; mensajes técnicos privados e identificador público; display_errors desactivado | Prueba de no divulgación del mensaje técnico |
| 10 Auditoría | Auditoría de reservas/pagos dentro de la transacción | Fallo inducido de auditoría revierte el cambio de negocio |
| 11 Reservas cerradas | Rechazo de pago/aprobación en estados finales o con cancelación pendiente | Pruebas de política y reintento HTTP |
| 12 Accesibilidad | Labels asociados, diálogo declarado, foco inicial/contenido/retorno, fondo inert, botón de salida con nombre accesible | Navegador en dos tamaños; reglas seleccionadas de axe. No auditoría WCAG completa |
| 13 QA | Composer y lockfile, PHPUnit, PHPStan, controles de estilo, integración, concurrencia, restauración y Playwright; workflow CI | Comprobaciones locales. Workflow preparado, sin ejecución remota en GitHub |
| 14 Logout GET | Ambas rutas de salida exigen POST y CSRF; botón usa formulario | GET 405; salida válida y permanencia de sesión tras GET verificadas |
| 15 Mantenimiento | URL configurable, autoload Composer compatible con bootstrap, historial/checksum de migraciones y paginación de listados administrativos/reservas | Análisis estático, búsquedas y migración local aplicada |

## Datos y respaldo

Resultados locales: 11 casos de ingreso, 7 pruebas PHPUnit (13 aserciones),
22 comprobaciones de integración y 14 pruebas Playwright pasan; PHPStan nivel 5
sin errores. Concurrencia y restauración sintética pasan. Los controles de estilo
no tienen errores bloqueantes y conservan advertencias visibles. Composer y npm
no informaron vulnerabilidades conocidas al consultar sus bases de avisos.

No se borraron ni alteraron usuarios, reservas, pagos o egresos existentes para
probar. Se creó un usuario SQL nuevo de permisos SELECT/INSERT/UPDATE/DELETE
sobre la base de aplicación. Sus credenciales están en `backend/config/local.php`,
ignorado por Git y bloqueado por Apache.

La migración aditiva agrega una columna generada y un índice único a payments.
No convierte ni elimina pagos. Antes de aplicarla se guardó
`backend/storage/before-hardening.sql`; el acceso HTTP a storage está bloqueado.
El historial está en `schema_migrations` y el ejecutor impide cambios de checksum
de migraciones ya aplicadas. El DDL no se presenta como reversible por una transacción.

Las bases de prueba usan nombres generados `devioz_test_*`, `devioz_browser_*`
y `devioz_restore_*`. No reutilizan la base operativa como fixture. La prueba de
restauración usa un respaldo sintético y verifica conteos en otra base; no
certifica tiempos de recuperación de un servidor de producción.

## Comandos de comprobación

Con PHP y Composer en PATH:

```text
composer install
composer quality
php tests/integration.php
php tools/browser-fixture.php create
php tests/concurrency.php
php tests/restore.php
npm ci --ignore-scripts
npx playwright install chromium
npm run test:e2e
php tools/browser-fixture.php clean
composer audit
```

En XAMPP, usar `C:\xampp\php\php.exe` y `tools\composer.phar`. La instalación
portable de Node local está en `tools/runtime/`; no se agrega al PATH global.
Playwright necesita `PHP_BIN=C:\xampp\php\php.exe` en ese entorno.

`composer quality` comprueba sintaxis, skills, los 11 casos previos de ingreso,
PHPUnit, PHPStan nivel 5 y estilo. Integración/navegador son comandos separados
porque requieren una base y Chromium. CI ejecuta también esas comprobaciones.

El control de estilo se basa en PSR-12 con excepciones explícitas para las clases
globales y excepciones agrupadas del bootstrap heredado. Las advertencias de
longitud de línea/efectos de inicialización son visibles y no bloquean la entrega;
los errores de estilo sí. No se afirma conformidad PSR-12 estricta de toda la app.

## Publicación y operación

1. Usar variables del entorno descritas en `.env.example`; el archivo no se
   carga automáticamente. `local.php` sólo se utiliza fuera de producción.
2. Definir dominio, certificados y configuración privada del servidor. La
   plantilla `deploy/apache-production.conf.example` incluye redirección HTTPS,
   TLS y ocultación de versión. No fue aplicada sobre otros sitios de XAMPP.
3. Configurar usuario SQL limitado para runtime y credenciales separadas para
   migraciones. En producción el ejecutor requiere DB_MIGRATION_USERNAME.
4. Configurar almacenamiento privado durable y respaldos fuera del despliegue.
   El contador de intentos usa archivos: para varios servidores se necesita
   almacenamiento compartido con bloqueo fiable o un contador distribuido.
5. Para una base ya existente, respaldar primero y ejecutar
   `php tools/migrate.php --adopt-existing`. Para una base nueva, importar el
   esquema y migraciones históricas según `docs/development-quality.md`, sin
   importar cuentas demo en producción. No ejecutar los scripts migrar_*.sh
   heredados: regeneran código anterior y pueden deshacer las correcciones.
6. Probar las URLs bloqueadas, permisos y cookies Secure en el servidor objetivo.
   El modo producción falla si falta HTTPS directo o la configuración mínima.
   Un proxy debe configurar HTTPS en el servidor de origen de manera controlada;
   no se acepta X-Forwarded-Proto enviado arbitrariamente por un cliente.
7. Actualizar y validar PHP/MariaDB/Apache para producción. Esta tarea no sustituye
   el XAMPP compartido ni instala una actualización mayor del motor existente.

Una reversión debe conservar los cambios previos del usuario. El índice aditivo
puede mantenerse al revertir código. Antes de retirar una restricción, evaluar
integridad y respaldar. Una restauración del backup anterior sobre la base actual
puede perder movimientos posteriores: restaurar primero en otra base y verificar.

## Límites de la evidencia

Se revisaron capturas del panel y landing y se ejecutaron flujos seleccionados
en escritorio/móvil. Quedan para el despliegue real la emisión de certificado,
revisión integral con lector de pantalla, prueba de carga representativa,
streaming de cámaras reales, disponibilidad y recuperación operativa. Las
skills y resultados verdes no certifican automáticamente seguridad o accesibilidad.
