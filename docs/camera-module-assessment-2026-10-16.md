# Evaluación y diseño del módulo de cámaras

Actualización posterior: la [primera etapa con webcam](camera-webcam-phase-one.md)
documenta lo implementado después de esta evaluación. Los hallazgos siguientes
describen el estado previo y no deben interpretarse como una lista actualizada
de fallos pendientes.

Fecha: 16/10/2026. Alcance confirmado: un DVR Hikvision con cuatro canales
analógicos físicos; primera prueba con la cámara de la laptop. El modelo exacto,
firmware, códecs y disponibilidad de grabaciones en disco no están confirmados.

## Dictamen

La aplicación tiene una base de configuración y monitoreo HLS, pero todavía no
implementa la grabación ni la consulta histórica solicitada. No basta conectar
la laptop para obtener calendario, recortes y exportación: faltan captura,
almacenamiento, índice temporal, reproducción de archivo y procesamiento de vídeo.

No se cambiaron cámaras operativas ni se activó la webcam en esta evaluación.
El objetivo es conservar PHP/PDO/Bootstrap y ampliar el módulo por etapas;
no sustituir la aplicación ni modificar su despliegue como efecto secundario.

## Capacidades actuales comprobadas en código

| Capacidad | Estado y límite |
| --- | --- |
| Configurar cámaras | Alta, edición, eliminación, canal, ubicación y ajustes locales. |
| Vista de cuatro canales e individual | Implementadas; no acreditan recepción de vídeo real. |
| Reproducción HLS | Implementada para una URL web compatible que ya exista. No hay puente RTSP dentro del proyecto. |
| WebRTC | Opción guardable, pero conexión no implementada. |
| Captura | Descarga PNG de un fotograma disponible; requiere acceso válido al vídeo/CORS. No guarda un vídeo. |
| Brillo, contraste, saturación y zoom | Ajustes del visor; no del DVR ni de sus archivos originales. |
| Administración | Permisos de administrador, CSRF y SQL parametrizado existentes. |
| Laptop, grabación, biblioteca, recortes y compartir | Sin implementación. |

Referencias: [pantalla](../frontend/admin/camaras.php),
[controlador](../backend/controllers/CameraController.php),
[modelo](../backend/models/Camera.php),
[visor](../frontend/assets/js/app.js),
[esquema](../database/cameras_hmi.sql).

## Hallazgos y prioridades

Las líneas corresponden al estado revisado, antes de futuras modificaciones.

| Prioridad | Hallazgo | Evidencia | Corrección requerida |
| --- | --- | --- | --- |
| Alta | No existe historial ni persistencia de vídeo. | `Camera.php:7`, `database/sql/schema.sql:242`; módulo JS desde `app.js:2176`. | Crear almacenamiento privado, índice de grabaciones y biblioteca por fecha/hora. |
| Alta | No hay fuente de webcam. | Sin `getUserMedia` ni `MediaRecorder` en el módulo. | Fuente de prueba explícita que no sobrescriba los canales físicos del DVR. |
| Alta | Guardar una ruta RTSP no produce un vídeo para el navegador. | `app.js:2267` y `:2286`; `camaras.php:98`. | Adaptador privado del DVR y servicio de vídeo compatible cuando se integre el equipo. |
| Alta | WebRTC seleccionable sin conexión implementada. | `camaras.php:100`, `app.js:2269`. | Deshabilitar la opción hasta implementar y probar el protocolo. |
| Alta | Controles habilitados sin señal o con cámara inactiva. | `app.js:2263`, `:2267`, `:2415`. | Derivar acciones de capacidad y estado; conservar el estado inactivo al pulsar controles. |
| Alta | La sesión PHP no demuestra protección del vídeo externo. | `app.js:2286`. | Verificar autorización del manifiesto HLS y de todos sus segmentos; no se evaluó un endpoint real. |
| Media | El cambio de calidad no cambia la señal. | `app.js:2523`. | Selector sólo si existen ambas fuentes y se puede conmutar; de otro modo mostrar la calidad como información. |
| Media | Probar y reiniciar ejecutan el mismo bloque. | `app.js:2489`. | Una acción clara de reconexión, o pruebas diferenciadas con resultado definido. |
| Media | Entrar en configuración puede dejar reproductores ocultos activos. | `app.js:2379`, `:2403`. | Ciclo de vida explícito y una sola fuente activa cuando corresponda; nunca detener grabación persistente por cambiar de pestaña. |
| Media | Se pierde el regreso a vista individual al configurar desde la vista previa de diagnóstico. | `app.js:2382`. | Preservar origen de navegación y selección. |
| Media | Audio local y global pueden contradecirse. | `app.js:2470`. | Estado compartido y disponibilidad real de pista de audio. |
| Media | IP y puertos del DVR son texto fijo; HTTP 8000 es una etiqueta confusa. | `camaras.php:96`. | Configuración real del dispositivo; distinguir puerto web del puerto de servicio/SDK. |
| Media | Hora del visor no equivale a hora grabada. | `app.js:2535`. | Mostrar zona Lima y diferenciar reloj del visor de marca temporal del archivo o DVR. |
| Media | La captura no incluye el nombre/fecha del OSD HTML. | `app.js:2437`. | Definir captura original o anotada y dibujar las marcas si se anuncia esa función. |
| Media | Error fatal HLS sin reconexión automática propia. | `app.js:2279`. | Reintentos limitados, espera progresiva y aviso; no ciclos infinitos ni falsa conexión. |
| Media | Superficies oscuras fijas aun con tema claro. | `styles.css:3802`. | Respetar variables del tema compartido y conservar fondo oscuro donde favorezca el vídeo. |
| Media | Falta prueba específica de flujos multimedia. | `tests/browser` y `tests/integration.php`. | Añadir pruebas funcionales conforme se implemente cada capacidad, además de validación física. |

## Interfaz propuesta

Tres áreas principales: **En vivo**, **Grabaciones** y **Configuración**. La
biblioteca puede incluir un filtro o vista de **Fragmentos guardados**.

### En vivo

- Seleccionar estacionamiento y cámara. Mantener cuatro canales para el DVR.
- Seleccionar expresamente la fuente de prueba de este equipo, fuera de la
  asignación de canales físicos. Una webcam no se presenta como cuatro cámaras.
- Mostrar estado de conexión separado de habilitación y grabación.
- Usar etiquetas en español y acciones concretas: Conectar, Desconectar,
  Capturar imagen, Iniciar grabación y Detener y guardar, cuando sean compatibles.
- Mostrar temporizador y advertencia visible durante la grabación de prueba.
- Activar audio sólo si existe pista. Pedir micrófono aparte si se necesita.
- Pausar imagen no significa detener una grabación del DVR.

### Grabaciones: días → horas → fragmento

Ejemplo de navegación propuesto, no historial existente:

```text
Cámara elegida
  ├─ [16 octubre · 3 grabaciones]
  │      ├─ [09:00–09:12]
  │      ├─ [11:30–11:42]
  │      └─ [15:00–15:08]
  └─ [15 octubre · 1 grabación]

Elegir un tramo → Reproducir → Inicio 11:32:10 / Fin 11:33:00
               → Preparar fragmento → Vista previa → Descargar / Compartir
```

Las tarjetas se generan con material guardado o encontrado en el DVR. Los días
sin vídeo no aparentan tener contenido. La vista de horas indica discontinuidades
y cortes de señal. Un rango no puede exportar material inexistente entre tramos.
La selección que cruce medianoche debe conservar la fecha de inicio y de fin.

Cada archivo muestra cámara/fuente, fecha/hora de Lima, duración, tamaño y estado.
Un fragmento que deba conservarse como evidencia se distingue de la grabación
ordinaria sujeta a rotación.

### Contrato de botones

| Botón | Cuándo está disponible | Resultado verificable |
| --- | --- | --- |
| Usar cámara de este equipo | Administrador, navegador compatible y contexto permitido. | Solicitud de permiso; señal real o explicación de permiso denegado/dispositivo ocupado. |
| Reproducir / Pausar imagen | Fuente lista o reproducción pausada. | Cambia visor, etiqueta y estado; no cambia la grabación del DVR. |
| Capturar imagen | Fotograma disponible y captura permitida. | Imagen descargable; error claro si falla. |
| Iniciar grabación de prueba | Webcam conectada y grabador disponible. | Indicador y contador activos. |
| Detener y guardar | Grabación activa. | Finalizar, subir y confirmar persistencia; no anunciar guardado antes de recibir confirmación. |
| Guardar fragmento | Rango válido dentro de material disponible. | Trabajo de exportación: pendiente, procesando, listo o fallido. |
| Descargar | Archivo listo y usuario autorizado. | Archivo reproducible con formato/nombre correctos. |
| Compartir archivo | Archivo listo y soporte del navegador/sistema. | Selector del sistema; cancelación tratada como cancelación, no éxito. |
| Reconectar | Fuente desconectada o fallida. | Nuevo intento controlado y resultado visible. |
| Calidad | Dos fuentes publicadas y compatibles. | Cambio real de fuente; mantener la anterior si falla. |

## Arquitectura propuesta

Mantener controladores PHP, PDO, Bootstrap y JS sin compilación. Separar los
efectos multimedia en servicios con capacidades explícitas; no crear una aplicación
paralela ni lanzar conversiones largas dentro de una petición web.

### Etapa de laptop

```text
Webcam → getUserMedia → visor + MediaRecorder
                      → carga autenticada → archivo privado + índice temporal
                                           → biblioteca y reproducción
                                           → trabajo de recorte/conversión
                                           → descarga / compartir archivo
```

El navegador solicita permisos sólo tras una acción del administrador. En
`localhost` puede probarse; el acceso desde otro equipo por una IP HTTP no tiene
las mismas garantías de contexto seguro. La grabación debe detectar el formato
soportado; un archivo WebM no se vuelve MP4 cambiando su extensión.

Para sesiones largas se necesitan segmentos válidos, límites y control de memoria;
no acumular indefinidamente todos los datos en RAM. No asumir que cada bloque de
`MediaRecorder` se puede reproducir independientemente sin validarlo/procesarlo.
El servidor valida formato real, tamaño, duración y estado final del archivo.

Cerrar la pestaña, suspender la laptop, perder la cámara o apagar el equipo
interrumpe esta fuente de prueba. Mostrarlo claramente. No sustituye un grabador
de vigilancia que deba operar sin un navegador abierto.

### Etapa de DVR

```text
Cuatro cámaras analógicas → DVR Hikvision
                              ├─ RTSP por canal → puente de vídeo → visor web
                              └─ grabaciones en disco → adaptador de búsqueda/descarga
                                                       → biblioteca y exportaciones
```

Primero identificar modelo/firmware y consultar capacidades de forma autorizada.
Si el DVR ya graba y permite buscar/descargar archivos, priorizar su archivo
histórico para evitar duplicación innecesaria. ISAPI/SDK son opciones que deben
confirmarse para el equipo, no prestaciones garantizadas por la marca.

Si no se puede consultar su historial o se requiere grabación independiente,
evaluar un servicio local de grabación de RTSP. MediaMTX es una opción para
publicación, grabación y reproducción; FFmpeg puede encargarse de conversión
y recortes. Son dependencias de implementación propuestas, no instaladas aquí.
Cambiar RTSP a HLS/WebRTC no convierte automáticamente un códec incompatible.

Para exportación interoperable, objetivo práctico: MP4 con H.264 y audio AAC
cuando exista. El recorte exacto puede requerir recodificación; la copia sin
recodificar puede empezar desde un fotograma clave anterior al instante pedido.
Verificar duración, resolución, audio, límites y reproducción del archivo resultante.

### Persistencia y operación

Ampliaciones de diseño, no migraciones ejecutadas:

- Fuente/dispositivo: clase de fuente, cuatro canales DVR y capacidades reales.
- Grabaciones: fuente, comienzo/fin, duración medida, formato, tamaño,
  referencia privada y estado de almacenamiento. Fechas normalizadas y conversión
  explícita a Lima en consultas y presentación.
- Exportaciones: grabación/rango origen, usuario solicitante, estado del trabajo,
  archivo resultante y fallo controlado. Reintentos sin duplicar archivos.
- Procesador de trabajos: ejecución en segundo plano, límites de concurrencia,
  argumentos permitidos y rutas generadas, sin interpolar comandos del usuario.
- Reproducción/descarga: autorización de servidor y soporte HTTP Range para buscar
  dentro del vídeo; nunca aceptar una ruta arbitraria enviada por el navegador.
- Secretos DVR fuera de HTML/JSON, repositorio y registros públicos. Autorizar
  también al servicio que entregue playlists, segmentos y grabaciones.
- Retención y cuota de disco acordadas antes de automatizar borrados. La regla
  previa de cinco días para auditoría no define la retención de vídeos.
- Supervisar disco lleno, carga incompleta, apagado durante exportación, pérdida
  de red y reinicio de servicios; conservar mensajes útiles para el administrador.

Ejemplo de dimensionamiento, no medición del DVR: 2 megabits/s constantes equivalen
a unos 21,6 GB decimales por día y por cámara; cuatro cámaras suman 86,4 GB/día,
sin margen adicional. Se necesita medir el bitrate real y acordar días de conservación.

## Compartir por WhatsApp y otros medios

El flujo base debe funcionar sin integrar una API de mensajería:
preparar archivo → vista previa → compartir desde el sistema cuando sea compatible,
o descargar y adjuntar manualmente. El navegador no garantiza que WhatsApp esté
instalado ni que aparezca como destino. Un enlace a WhatsApp con texto no adjunta
automáticamente un vídeo local.

Un enlace `localhost` no permite a otra persona abrir el archivo desde su teléfono
o equipo. Compartir por enlace externo sería una capacidad adicional: alojamiento
accesible, HTTPS, token temporal y revocación. No es necesario para compartir el
archivo descargado. Auditar la generación y entrega del archivo; no afirmar
recepción por el destinatario sin confirmación del medio correspondiente.

## Plan de implementación y aceptación

| Etapa | Entrega | Criterio de aceptación |
| --- | --- | --- |
| 1 | Estados coherentes y fuente laptop. | Ver imagen real; permiso denegado/ausencia/ocupación explicados; pistas liberadas al desconectar; controles válidos en cada estado. |
| 2 | Grabación de prueba y persistencia privada. | Grabar, detener, confirmar guardado y reproducir tras recargar; recuperar o descargar el resultado si falla su carga. |
| 3 | Biblioteca por día/hora. | Sólo muestra tramos existentes; reconoce huecos; consulta exacta y cruce de medianoche. |
| 4 | Recorte, MP4 y compartir. | Inicio/fin válidos, archivo reproducible, progreso y fallo controlados, descarga y alternativa cuando compartir no está disponible. |
| 5 | Adaptador del DVR. | Un canal real en vivo y un archivo histórico comprobados; luego cuatro canales y prueba de audio si existe. |
| 6 | Operación y entrega. | Permisos, CSRF, Range, disco lleno, desconexión/reconexión, reinicios, retención acordada y prueba sostenida con el cliente. |

No se considera completado un bloque sólo por mostrar sus botones. La validación
de webcam comprueba esa fuente y el flujo del archivo; no acredita compatibilidad
con el DVR ni grabación continua.

## Evidencia de esta evaluación

Revisión de frontend, controlador, modelo, esquema y documentación; contraste
con documentación primaria de fabricantes y navegadores. Calidad base ejecutada
con `C:\xampp\php\php.exe tools/quality.php`: lint de 107 archivos, 11 casos de
ingreso, PHPUnit 7 pruebas/13 aserciones y PHPStan aprobados; estilo con advertencias
existentes, sin errores bloqueantes.

Sondeo Playwright ejecutado en escritorio (1440 × 900) y móvil (390 × 844), con
una base sintética recién creada y eliminada al terminar. Los dos recorridos
finalizaron (18,8 segundos en conjunto). Se comprobó:

- Crear configuración sintética y abrir la vista individual sin señal.
- Los controles de reproducción, audio y captura permanecen habilitados sin señal.
- Elegir calidad principal restituye `sub` y muestra una instrucción de configuración.
- Pulsar Play con la cámara inactiva cambia el estado de `Inactiva` a `Esperando señal`.
- Una cámara activa configurada como WebRTC muestra que requiere integración.
- Configurar desde la vista previa de diagnóstico pierde el regreso a individual.
- Un conductor recibe HTTP 403 y un POST con CSRF inválido se rechaza.
- No hubo desbordamiento horizontal de página en los tamaños comprobados.
- No hubo infracciones en las reglas seleccionadas de axe (labels, nombres de
  botones y contraste) en la vista individual sin señal de ambos temas. Esto
  no es una auditoría de accesibilidad de todas las vistas/estados.
- El fondo del módulo sigue oscuro en ambos temas (`rgb(6, 11, 20)`).

Estos recorridos documentan los fallos; no los corrigen ni acreditan reproducción
de vídeo real. Se revisaron capturas de escritorio y móvil. Evidencia local en
`backend/storage/camera-review-{desktop,mobile}.json`,
`backend/storage/camera-review-browser.log` y
`test-results/cameras-analysis-{desktop,mobile}-{light,dark}.png`.
El sondeo elimina la cabecera del router de pruebas antes de pedir dependencias
CDN; no se interpreta un bloqueo CORS de esa cabecera sintética como fallo del cliente.

No se conectó el DVR ni se abrió la cámara física. No se evaluaron sus credenciales,
disco, audio, códecs, rendimiento sostenido o disponibilidad de archivos históricos.

## Documentación primaria consultada

- [Hikvision: formato RTSP](https://supportusa.hikvision.com/a/solutions/articles/17000129064).
- [Hikvision: puertos y funciones](https://enpinfo.hikvision.com/hkwsen/unzip/20230410194813_20373_doc/GUID-E852C255-D9BD-4288-959E-584EC975B407.html).
- [Hikvision: búsqueda/descarga de vídeo con ISAPI, guía para NVR](https://www.hikvisioneurope.com/eu/portal/portal/Technology%20Partner%20Program/03-How%20to/How%20to%20search%20and%20download%20the%20video%20file%20from%20NVR%20via%20ISAPI.pdf), referencia de integración, sin confirmar soporte del DVR del cliente.
- [MDN: permisos y contexto de getUserMedia](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).
- [MDN: detección de formatos MediaRecorder](https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder/isTypeSupported_static).
- [MDN: compartir archivos](https://developer.mozilla.org/en-US/docs/Web/API/Web_Share_API).
- [FFmpeg: opciones y búsqueda precisa](https://www.ffmpeg.org/ffmpeg.html).
- [MediaMTX: reproducción del archivo grabado](https://mediamtx.org/docs/features/playback).
