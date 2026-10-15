---
name: devioz-database
description: Diseñar o revisar consultas PDO y migraciones MariaDB de DEVIOZ, incluyendo integridad financiera y concurrencia de reservas.
---

# Datos de DEVIOZ

Antes de proponer SQL, leer el esquema en `database/sql/schema.sql`, las
migraciones adicionales y los modelos afectados. Si se consulta la base local,
comparar el esquema real con los archivos; no asumir que una migración fue aplicada.

Mantener importes en DECIMAL en persistencia. El saldo proviene de pagos
aprobados menos egresos; no corregirlo ocultando valores negativos ni borrando
movimientos sin que el usuario solicite esa corrección de datos.

Separar parámetros de valores y nombres SQL. Para filtros o columnas dinámicas
usar listas permitidas. Evaluar índices con consultas representativas y EXPLAIN,
sin crear índices por intuición ni registrar datos personales de ejemplo.

Reserva, plaza y pago forman un flujo concurrente: inspeccionar transacciones,
bloqueos y restricciones para impedir doble asignación y aprobación duplicada.
Probar carreras con conexiones separadas en una base de prueba aislada cuando
el cambio afecte ese comportamiento.

Entregar migraciones versionadas, precondiciones y procedimiento de recuperación.
Identificar operaciones que MariaDB confirma implícitamente; no prometer rollback
de DDL. Verificar respaldo y restauración antes de aplicar migraciones destructivas
autorizadas. No reparar tablas internas de XAMPP como parte de una migración de la app.

Pruebas con datos deben usar otra base, credenciales limitadas y limpieza acotada.
Registrar el SQL/migración ejecutado y su resultado sin exponer secretos.
