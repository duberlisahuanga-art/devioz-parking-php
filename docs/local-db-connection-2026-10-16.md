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
