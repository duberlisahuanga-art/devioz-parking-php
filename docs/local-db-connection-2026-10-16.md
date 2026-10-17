# Conexión local restaurada — 16/10/2026

La landing devolvía 503 y el registro Apache mostraba error SQL 1045.
MariaDB de XAMPP escuchaba en el puerto 3306 y la base configurada existía,
pero mysql.user no contenía ninguna entrada para la cuenta runtime configurada.
No se determinó por qué desapareció esa cuenta; no atribuirlo al módulo de correo.

Se ejecutó `php tools/restore-local-db-account.php --apply`: recreación exclusiva
de la cuenta devioz_app_* ausente, con la contraseña de configuración privada y
los permisos por tabla de AuditPermissions. No se modificaron tablas internas
manualmente, credenciales root, datos del negocio ni configuración SMTP.
La verificación de INSERT en auditoría usa una transacción que se revierte.

Conexión de aplicación aceptada. SELECT/INSERT en auditoría permitidos y
UPDATE/DELETE rechazados. Tabla de recuperación accesible. Landing y login
respondieron HTTP 200 y la landing ya no mostró el error de conexión.

Diagnóstico: `php tools/check-local-database.php` no imprime contraseñas.
La herramienta de restauración requiere CLI, desarrollo local, cuenta dedicada
ausente y --apply; rechaza cuentas existentes y producción. Si falla la concesión
o validación, retira sólo la cuenta recién creada. La operación afecta privilegios
SQL y confirma implícitamente; no se presenta como transacción de datos.

## Nuevo incidente de arranque comprobado

La portada volvió a mostrar error de conexión. Apache estaba activo, pero no
había proceso MariaDB ni escucha en el puerto 3306: el diagnóstico PDO devolvía
2002. Se inició `mysqld.exe` con el `my.ini` habitual de XAMPP y `--standalone`.
No se instaló un servicio ni se cambió el inicio automático de Windows.

Al iniciar, MariaDB detectó y reparó automáticamente `mysql.global_priv` y
`mysql.tables_priv`, registrando `Table is fixed` y después `ready for connections`.
La cuenta dedicada de la aplicación estaba ausente y el acceso devolvía 1045.
Se recreó mediante `tools/restore-local-db-account.php --apply`, conservando
los valores privados y los permisos restringidos de auditoría.

Verificación final: `CHECK TABLE mysql.global_priv, mysql.tables_priv` devolvió
`OK` para ambas, la conexión de aplicación fue aceptada y portada/login
respondieron HTTP 200 sin el mensaje de error. Se abrió la portada en el navegador.
No hubo reparación manual ni borrado de archivos del motor o datos del negocio.

La reparación y los avisos de tablas no cerradas correctamente son compatibles
con un cierre no ordenado. No se confirmó por qué desapareció la cuenta y no
debe atribuirse a la reparación automática sin más evidencia. Para futuros
arranques se necesitan Apache y MySQL activos en XAMPP; detener el motor de
forma normal desde el panel, evitando terminar su proceso forzosamente.
