# Cámaras: biblioteca por día y hora

Implementación del 17/10/2026. Amplía la
[grabación manual con webcam](camera-recording-2026-10-16.md): la biblioteca
sustituye la lista inicial de 20 vídeos recientes por consultas de día y hora
con paginación. Conserva el grabador, los límites y el almacenamiento privado
de la etapa anterior.

## Uso

1. Seleccionar el estacionamiento en **Cámaras** y pulsar **Ver grabaciones**.
   El acceso lleva directamente al calendario; no requiere conectar la webcam.
   La biblioteca también está disponible debajo de **Cámara de este equipo**.
2. En **Grabaciones guardadas**, utilizar **Mes de grabaciones**, **Mes anterior**
   o **Mes siguiente** para consultar un mes. El calendario comienza en lunes y
   representa sus 28, 29, 30 o 31 días.
3. Cada caja de día muestra la cantidad de vídeos disponibles. Un guion indica
   que no hay vídeos; el día sigue siendo seleccionable para consultar su estado.
4. Seleccionar un día y después **Todas las horas** o una de las 24 cajas de hora,
   desde 00:00 hasta 23:00. Los contadores indican los vídeos disponibles dentro
   del intervalo seleccionado.
5. Utilizar **Reproducir**, **Descargar** y, cuando corresponda, **Anterior** o
   **Siguiente**. Las páginas contienen hasta 20 vídeos, en orden cronológico
   ascendente de inicio, con el identificador interno como desempate estable.

La fecha y las horas de inicio y fin se muestran en **America/Lima**, junto con
duración, tamaño y formato. Los intervalos sin vídeos indican ausencia de
fragmentos guardados, no una grabación continua disponible para reproducir.

Al abrir la biblioteca sin filtros se consulta el último día con grabaciones;
si no existen, se utiliza la fecha de Lima. Cambiar el estacionamiento limpia
la selección anterior. La URL conserva estacionamiento, mes, fecha, hora y página
mediante los parámetros `recordings_parking`, `recordings_month`,
`recordings_date`, `recordings_hour` y `recordings_page`, para recuperar la
consulta después de recargar. No se incluyen credenciales ni tokens en esos
parámetros.

**Actualizar grabaciones** vuelve a consultar la biblioteca. Al guardar un vídeo
nuevo, se abre su fecha real de inicio y la última página correspondiente para
mostrarlo aunque el día ya tenga más de 20 vídeos.

## Límites de los intervalos y conteos

Un vídeo ocupa el intervalo **[inicio, fin)**: incluye el instante inicial y
excluye el final. Aparece en cada día y cada hora con los que tenga un solapamiento
real. Por ejemplo, un vídeo de 23:59:30 a 00:00:30 aparece en ambos días; si
termina exactamente a las 00:00:00, corresponde únicamente al día anterior.
El mismo criterio se aplica al cambio de hora y de mes.

El vídeo conserva su identidad y sus fechas completas; aparecer en dos cajas
no crea dos archivos. Por ello, sumar contadores de horas o de días puede dar
un resultado mayor que el número de vídeos únicos. **Todas las horas** muestra
el total de vídeos únicos que coinciden con el día seleccionado.

## Datos, acceso y alcance

Se reutiliza la tabla `camera_recordings`; esta etapa no cambia el esquema ni
requiere migraciones o correcciones de datos existentes. Las consultas usan
parámetros PDO, estacionamiento validado y rangos de fecha calculados en Lima.
El servidor comprueba fechas reales, meses entre los años 1000 y 9998, horas
de 00 a 23 y páginas enteras entre 1 y 100000.

El endpoint administrativo `frontend/admin/grabaciones.php` incorpora:

- `GET action=calendar&parking_id=...&month=AAAA-MM`: resumen del mes y sus días
  con vídeos; si se omite el mes, el servidor elige el último disponible.
- `GET action=list&parking_id=...&date=AAAA-MM-DD&page=...`: vídeos del día y
  contadores de sus 24 horas; `hour=0` a `hour=23` limita el intervalo.
- La consulta `action=list` sin fecha conserva la respuesta de vídeos recientes
  para compatibilidad con el flujo anterior.

Todos estos accesos requieren una sesión administrativa activa. Las rutas de
archivos permanecen privadas, y la reproducción y descarga mantienen las
comprobaciones de la etapa anterior. Los errores de consulta se presentan como
errores, sin afirmar que un día esté vacío cuando el servidor no respondió.

La biblioteca organiza los fragmentos guardados de la webcam; no añade captura
24/7, grabaciones del DVR, recorte, conversión de formato ni envío por WhatsApp.
Esas funciones requieren sus propias implementaciones y pruebas.

## Evidencia de validación

- `C:\xampp\php\php.exe tests\camera_recording_library.php`: **49 comprobaciones
  correctas**, realizadas sobre una base aislada. Cubren intervalos, cambios de
  día y hora, paginación, aislamiento por estacionamiento y validación de entradas.
- Pruebas nuevas de navegador y regresiones de webcam: la ejecución final de
  `camera-library.spec.js`, `camera-recordings.spec.js` y `cameras.spec.js` cubre
  **24 casos aprobados** en Chromium, escritorio (1440 × 900) y móvil (390 × 844). El resultado
  de cierre queda en `backend/storage/library-browser.log`.
- `C:\xampp\php\php.exe tools\quality.php`: baseline aprobado, incluyendo lint,
  política de ingreso, PHPUnit y PHPStan; se conservan avisos de estilo previos.
- Revisión visual realizada en ambos tamaños y temas. Axe no detectó
  infracciones en el módulo. Verificados teclado, retorno de foco al seleccionar
  días y páginas, ausencia de desbordamiento y apertura del reproductor visible.
- Los casos de navegador usan límites de autenticación intactos, con archivos
  separados por proyecto y caso dentro del router de pruebas. Las grabaciones
  reales se prueban explícitamente en el estacionamiento 1; los metadatos de
  calendario y paginación se reservan al estacionamiento 2.

Los registros sintéticos añadidos para comprobar calendario y paginación son
metadatos de prueba: no representan clips reproducibles. Las pruebas del grabador
siguen utilizando `MediaRecorder` real y el estacionamiento sintético 1 para
comprobar carga, persistencia y reproducción de archivos reales. Ninguna de estas
pruebas verifica la webcam física de la laptop ni los cuatro canales del DVR.

Durante la preparación de las pruebas, un intento de conceder permisos mediante
`GRANT` a una cuenta SQL de pruebas devolvió un error de Aria. Esta etapa no
repara tablas internas de MariaDB. Se continuó con la conexión de pruebas
configurada y una base generada, como en `tests/integration.php`; esta ejecución
no verifica mínimo privilegio SQL y no usa datos operativos como fixture.
La comprobación posterior confirmó cero bases o usuarios temporales de esta
prueba pendientes y acceso de consulta correcto con la cuenta habitual de la app.
Queda pendiente una revisión operativa del estado de MariaDB y de los permisos
de la cuenta de pruebas, separada de esta mejora de biblioteca.

Pasar las comprobaciones anteriores no certifica accesibilidad completa,
seguridad del despliegue ni disponibilidad para producción. Continúan pendientes
las pruebas físicas de webcam y DVR, el recorte y las opciones de compartir de la
hoja de ruta del módulo.
