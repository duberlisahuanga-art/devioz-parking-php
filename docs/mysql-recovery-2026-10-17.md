# Recuperación de MySQL de XAMPP — 17 de octubre de 2026

## Incidente y diagnóstico

MySQL se cerraba al intentar iniciarlo desde XAMPP. No había otro proceso
escuchando en el puerto 3306. MariaDB 10.4.32 registró un índice corrupto en
`mysql.db`; en una copia aislada también se confirmó corrupción de la página
cero de `mysql.transaction_registry`, que podía cerrar el servidor al consultar
metadatos. Se encontraron errores Aria en otras tablas internas.

Estos errores explican el fallo de arranque. No se ha demostrado qué causó
originalmente la corrupción; un arranque correcto no garantiza que no se repita.
No se cambió el puerto ni la configuración privada de conexión de DEVIOZ.

## Recuperación realizada

1. Con MySQL detenido se copió íntegramente su directorio de datos a un
   directorio privado de incidentes bajo `C:\xampp\mysql\recovery-backups`.
   Se verificaron por SHA-256 los 201 archivos (113.528.109 bytes) del respaldo.
   También se conservó la configuración del servidor.
2. Se trabajó en una copia, escuchando únicamente en `127.0.0.1:3307`.
   La reparación inicial de índices internos permitió extraer las bases,
   pero no resolvía la corrupción InnoDB; por ello no se aplicó como solución
   definitiva al directorio original.
3. Se exportaron lógicamente las cuatro bases existentes, incluidos rutinas,
   eventos y triggers. Se inventariaron las 48 tablas de esas bases mediante
   recuentos y hashes de todas sus filas. Se conservaron por separado las
   cuentas y los metadatos recuperados de las tablas internas Aria.
4. Se inicializó un directorio nuevo con la utilidad de la misma instalación
   de MariaDB y se restauraron las bases y los metadatos recuperados. Se
   comprobaron las tablas y la conexión de la cuenta configurada en DEVIOZ
   con la autenticación normal activa.
5. Después del cierre limpio de la instancia de prueba, se conservó el
   directorio original completo dentro del incidente y se instaló la
   restauración verificada como `C:\xampp\mysql\data`.
6. Se inició el servicio con la configuración habitual, sin modos de
   recuperación ni omisión de permisos, en el puerto 3306. Se verificó
   nuevamente después de detenerlo y arrancarlo de forma limpia.

Los datos de negocio no se editaron para corregir este incidente. Las tablas
internas dañadas se reconstruyeron; sus archivos originales siguen conservados.
Los archivos de respaldo, SQL, cuentas y registros privados no se incluyen en Git.
El trabajo pendiente del editor de fragmentos de cámara se mantuvo separado.

## Evidencia

- Las 48 tablas de las cuatro bases coinciden por recuento y SHA-256 con
  el inventario anterior a la restauración, también después del reinicio.
- `mysqlcheck --all-databases --check --silent` terminó con código 0.
- Las consultas de metadatos y de las tablas internas InnoDB reconstruidas
  terminaron correctamente.
- La cuenta configurada de la aplicación pudo conectar y consultar usuarios
  con los permisos activos.
- `frontend/index.php` respondió HTTP 200 sin el mensaje de error de conexión,
  antes y después del reinicio normal del servicio.
- `php tools/quality.php` terminó con código 0: lint de 118 archivos,
  11 casos de ingreso, PHPUnit (7 pruebas, 13 aserciones), PHPStan y estilo.
  El chequeo de estilo conserva sus advertencias existentes; no se modificaron
  esos archivos para esta recuperación.

Esto comprueba la recuperación del servicio y la conservación de las filas
inventariadas. No sustituye una prueba completa de todos los flujos de la app.

## Si vuelve a ocurrir

- Detener los intentos de inicio y guardar los registros de MySQL y los eventos
  de MariaDB de Windows. Comprobar procesos y puertos antes de iniciar otra copia.
- Con el motor detenido, respaldar todo el directorio de datos y la configuración
  antes de reparar. Probar la recuperación en otra carpeta y puerto.
- No sustituir tablas o archivos InnoDB individuales por archivos de la carpeta
  `backup` de XAMPP ni borrar `ibdata1` para intentar arrancar el proyecto.
- Para volver al estado anterior a esta intervención, primero detener MySQL y
  conservar íntegra la instancia actual; restaurar juntos el directorio original
  y su configuración guardada. Ese original contiene la corrupción del incidente
  y sirve para recuperación especializada, no como servicio ya reparado.
- Cerrar MySQL con **Stop** en XAMPP y esperar a que termine antes de apagar
  Windows. Mantener respaldos periódicos de bases y archivos, con restauración
  probada. Los commits de Git no contienen estos datos.

Referencia de las operaciones sobre Aria:
[REPAIR TABLE de MariaDB](https://mariadb.com/docs/server/reference/sql-statements/table-statements/repair-table).
Esta instrucción no repara tablas InnoDB.
