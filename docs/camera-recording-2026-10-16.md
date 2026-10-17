# Cámaras: grabación manual y guardado de la webcam

Implementación iniciada el 16/10/2026 y validada el 17/10/2026. Continúa la
[primera etapa de webcam](camera-webcam-phase-one.md) y la
[evaluación del módulo](camera-module-assessment-2026-10-16.md).
Las restricciones de aquella primera etapa sobre ausencia de grabación quedan
ampliadas por esta entrega.

## Uso por el administrador

1. Abrir **Cámaras**, seleccionar el estacionamiento y entrar en
   **Cámara de este equipo**.
2. Pulsar **Conectar cámara** y permitir el acceso del navegador. No se solicita
   micrófono ni se modifica un canal del DVR.
3. Con imagen disponible, pulsar **Iniciar grabación**. El contador muestra la
   duración. La pausa del visor y la desconexión se bloquean durante la captura;
   para finalizar, utilizar **Detener y guardar**.
4. Pulsar **Detener y guardar** y mantener la pestaña abierta hasta la confirmación
   **Grabación guardada en el sistema**. Alcanzar un límite también detiene la
   captura e intenta guardar el fragmento.
5. En **Grabaciones guardadas**, utilizar **Reproducir** o **Descargar**. Recargar
   la página conserva los vídeos que ya recibieron confirmación de guardado.

La lista muestra hasta 20 grabaciones recientes del estacionamiento seleccionado,
con fecha y hora de America/Lima, duración y tamaño. **Actualizar grabaciones**
reconsulta el servidor. Esta lista inicial todavía no es el calendario por días
y horas previsto para la siguiente etapa.

## Límites y recuperación de errores

- Grabación manual de hasta 2 minutos y 20 MiB por archivo. El límite de carga
  puede ser menor según `upload_max_filesize` y `post_max_size` de PHP; la interfaz
  muestra el límite efectivo que comunica el servidor.
- Cuota inicial de almacenamiento: 1 GiB, configurable con
  `CAMERA_RECORDINGS_QUOTA_MB=1024`. Se comprueba además el espacio libre y se
  reserva un margen de 64 MiB en disco. Alcanzar la cuota informa del problema;
  no borra automáticamente grabaciones guardadas.
- Se mantienen hasta tres inicios pendientes por administrador. Un inicio
  pendiente caduca después de una hora; al iniciar otra grabación se depuran
  los pendientes vencidos del mismo administrador.
- Ante un fallo de guardado, el vídeo permanece temporalmente en memoria de
  esa pestaña. Se puede revisar, **Reintentar guardado** o **Descargar copia**.
  El reintento utiliza el mismo identificador y evita duplicar un guardado
  confirmado por el servidor cuya respuesta se perdió.
- Para eliminar una captura pendiente hay que abrir
  **Descartar grabación sin guardar** y confirmar con el botón de descarte.
  Esta acción no elimina vídeos que ya están guardados en el servidor.
- No recargar ni cerrar la pestaña mientras exista un vídeo pendiente de guardar.
  Descargar una copia permite conservarlo fuera del sistema, pero no confirma
  su incorporación a la lista del servidor.

La captura depende de la pestaña, el permiso, la cámara y el estado del equipo.
Ocultar la pestaña detiene la grabación e intenta conservar el fragmento; cerrar
el navegador, suspender el equipo o perder el dispositivo puede interrumpirla.
La webcam de prueba no ofrece vigilancia continua ni grabación 24/7.

## Formato y acceso

Se utiliza `MediaRecorder` sin audio y el formato nativo que admita el navegador:
WebM, preferido cuando está disponible, o MP4. No se cambia únicamente la extensión
para simular otro formato y no hay transcodificación ni remultiplexado. La
compatibilidad para abrir o compartir el archivo descargado depende del formato
y del programa receptor; esta etapa no promete MP4 universal.

Los archivos se guardan en almacenamiento privado y los metadatos en
`camera_recordings`. La consulta, reproducción y descarga requieren una sesión
administrativa activa. Iniciar, guardar y descartar requieren además CSRF;
guardar o descartar un pendiente verifica quién lo inició. Las cargas se validan
en servidor y el guardado registra el evento de auditoría. La reproducción
admite peticiones parciales del vídeo para desplazarse por el archivo.

`CAMERA_RECORDINGS_PATH` permite definir la carpeta privada. El valor por defecto
es `camera-recordings` bajo el almacenamiento de la aplicación. En producción,
configurar una ruta fuera de la raíz pública, con escritura sólo para la cuenta
que ejecuta PHP. La carpeta incluye `.htaccess` con `Require all denied` como
protección adicional para Apache; no sustituye la ubicación privada.

Si se usa una carpeta bajo la raíz pública de Apache, comprobar que la URL
directa de un archivo de prueba devuelve **403**, y que el mismo vídeo se puede
abrir mediante el endpoint autenticado. Verificarlo con la configuración real
de Apache: la presencia de `.htaccess` por sí sola no demuestra su aplicación.
No añadir vídeos, configuración privada, credenciales ni respaldos a Git.

## Preparación local, migración y recuperación

La preparación local se realiza desde la raíz del proyecto:

```powershell
& C:\xampp\php\php.exe tools\configure-camera-recordings.php --apply
```

El procedimiento debe conservar un respaldo completo de MariaDB y verificar su
restauración en una base temporal antes de aplicar el DDL. Utiliza la cuenta de
mantenimiento para la migración aditiva
`database/migrations/20261016_camera_recordings.sql`, y concede a la cuenta
habitual de la aplicación únicamente los permisos de datos necesarios sobre
la tabla nueva. El servidor web no necesita permiso para crear tablas.

La migración crea una tabla con referencias a usuarios y estacionamientos;
no transforma sus registros existentes. El DDL de MariaDB puede confirmar
implícitamente la transacción: no debe tratarse como una operación reversible
mediante `ROLLBACK`.

Para recuperar el servicio o retroceder una versión, conservar tanto los vídeos
como sus metadatos, identificadores y relación con usuarios/estacionamientos.
Guardar de forma coherente la carpeta de grabaciones y la base de datos; Git
respalda el código, no esos archivos operativos. No recuperar una versión
eliminando la tabla, usuarios, estacionamientos ni clips existentes. Ante una
restauración, revisar integridad de los archivos y acceso autenticado antes de
reanudar nuevas grabaciones.

## Evidencia de validación

- `C:\xampp\php\php.exe tools\quality.php`: baseline aprobado; 114 archivos PHP,
  11 casos de ingreso, PHPUnit (7 pruebas/13 aserciones) y PHPStan sin errores.
  Persisten advertencias de estilo previas ajenas a esta mejora.
- Sintaxis de `webcam-recording.js` y `cameras.js` comprobada con `node --check`.
- `C:\xampp\php\php.exe tests\integration.php`: 110 comprobaciones correctas en
  una base aislada, incluyendo la instalación de la nueva migración.
- Playwright: `tests/browser/cameras.spec.js` y
  `tests/browser/camera-recordings.spec.js`, **16 pruebas aprobadas** en Chromium,
  escritorio (1440 × 900) y móvil (390 × 844). Se utilizó un `MediaStream` de
  canvas y `MediaRecorder` real; el servidor recibió y persistió archivos WebM
  reales, y el navegador los decodificó después de recargar.
- Verificados descarga, GET/HEAD/rangos y 416, rechazo de HTML camuflado,
  CSRF inválido, conductor y usuario no autenticado, propietario de pendientes,
  rechazo de sustitución de un archivo guardado, descarte explícito, navegación
  durante captura y reintento tras perder la respuesta de guardado. Este último
  renovó la sesión y su CSRF sin recargar la pestaña que conservaba el vídeo.
- Axe sin infracciones detectadas en el módulo en ambos temas; foco de control,
  ancho móvil y capturas revisados. Esta comprobación no certifica WCAG completo.
- Instalación local aplicada con respaldo completo restaurado en una base
  temporal y comparación de conteos de todas las tablas. La cuenta limitada
  accede a la tabla nueva. La siguiente ejecución reconoce la migración aplicada.
- Acceso HTTP directo al archivo sintético real: **403** tanto en el router de
  pruebas como bajo Apache local. Fixture y sus vídeos eliminados; un fixture
  anterior, si existía, se conserva. No se usaron datos operativos como fixture.

Los logs quedan en `backend/storage/recording-quality.log` y
`backend/storage/recording-browser.log`, y las capturas en `test-results/`.
Permanecen fuera de Git. La prueba física de la webcam de la laptop y la
compatibilidad de MP4 en otros navegadores siguen pendientes.

Los vídeos sintéticos de las pruebas no verifican el dispositivo físico ni el DVR.

## Continuación

La biblioteca por día y hora está implementada desde el 17/10/2026; consultar
[su uso y evidencia](camera-library-2026-10-17.md).

Quedan pendientes el recorte y exportación de
fragmentos, compartir mediante las opciones del dispositivo cuando sean
compatibles, y la integración real con los cuatro canales del DVR Hikvision.
Para el DVR falta confirmar modelo/firmware y capacidades de acceso a señales
y grabaciones. También sigue pendiente comprobar la entrega real del correo
de recuperación de contraseña por Gmail, fuera del alcance de esta etapa.
