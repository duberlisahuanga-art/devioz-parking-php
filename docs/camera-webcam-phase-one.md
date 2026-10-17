# Cámaras: primera etapa de prueba con webcam

Implementación del 16/10/2026. Continúa el diseño documentado en
[evaluación del módulo](camera-module-assessment-2026-10-16.md).

## Alcance

El administrador puede abrir **Cámara de este equipo** y solicitar la conexión
al pulsar **Conectar cámara**. Se usa vídeo sin micrófono. Esta fuente no se
guarda como un canal del DVR ni modifica las cuatro cámaras físicas configuradas.
No se graban, suben o almacenan vídeos en esta etapa.

Acciones disponibles: conectar, desconectar, pausar/reanudar imagen, descargar
captura PNG y ampliar la imagen si el navegador admite pantalla completa.
Pausar la imagen mantiene la cámara conectada; desconectar libera sus pistas.
Salir de la vista o abandonar la página también desconecta la webcam.

La cámara no se solicita al cargar la página. Una respuesta de permiso que llegue
después de cancelar o salir se descarta y sus pistas se detienen. Si el dispositivo
desaparece, se informa del error y se permite volver a conectar.

## Cómo probar en la laptop

1. Iniciar Apache y MariaDB y entrar como administrador.
2. Abrir Cámaras → Cámara de este equipo → Conectar cámara.
3. Permitir la cámara cuando el navegador lo solicite.
4. Comprobar imagen, resolución, pausa, reproducción y captura de imagen.
5. Desconectar y comprobar que el indicador de uso de cámara se apaga.

Para el entorno local, utilizar `http://localhost/devioz-parking-php/`.
El navegador requiere un contexto permitido y permiso del usuario; acceder por
una IP HTTP desde otro equipo no equivale a localhost.
[Referencia de getUserMedia](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).

Los errores de permiso, cámara ausente, cámara ocupada y fallo de conexión tienen
mensajes diferenciados. Si no aparece imagen, revisar también los permisos de
cámara del navegador y de Windows.

## Coherencia del visor DVR

- Reproducción, audio y captura se habilitan según fuente, estado e imagen.
  Una cámara inactiva conserva ese estado y no ofrece reproducción.
- La calidad es información del endpoint guardado: se retiró el selector que
  volvía al valor anterior sin cambiar la fuente.
- WebRTC se muestra como pendiente y no puede elegirse para una cámara nueva.
  Una configuración antigua se conserva al editar otros campos; no se convierte
  silenciosamente a HLS.
- Diagnóstico tiene una única acción de prueba sobre la conexión guardada.
- Abrir configuración libera los reproductores anteriores. Volver desde una
  vista previa mantiene el origen individual/gestión correspondiente.
- Audio global e individual reflejan el estado de las pistas detectadas.
- La fecha superpuesta indica explícitamente hora del visor en Lima.
- Los paneles respetan los temas claro y oscuro; el área de vídeo sigue oscura.

No se enviaron ajustes al DVR ni se comprobó una fuente física HLS en esta etapa.
La autenticación administrativa y CSRF existentes se mantienen.

## Implementación y validación

La lógica de cámaras se separó del archivo compartido `app.js` hacia
`frontend/assets/js/cameras.js`, cargado solamente por la pantalla de cámaras.
No hay framework, compilación, migración ni nueva dependencia de vídeo.

Verificación completada:

- `php tools/quality.php`: lint de 107 archivos, 11 casos de ingreso, PHPUnit
  7 pruebas/13 aserciones y PHPStan aprobados. Estilo sin errores bloqueantes,
  con advertencias existentes.
- `node --check`: sintaxis válida en `app.js`, `cameras.js` y el archivo de pruebas.
- `playwright test tests/browser/cameras.spec.js`: 8 casos aprobados entre
  escritorio (1440 × 900) y móvil (390 × 844), en la última ejecución (1,1 minutos).
- Cubiertos: solicitud explícita de permiso sin micrófono, captura PNG válida,
  pausa/reproducción, desconexión, cancelación y permiso tardío, salida de vista,
  pista interrumpida y pagehide. Incluye controles DVR inactivos/sin endpoint,
  protocolo WebRTC no seleccionable por teclado y preservación de configuración
  antigua al editar ubicación, acceso administrativo y rechazo de CSRF.
- Ambos temas, navegación inicial por teclado, ausencia de desbordamiento
  horizontal y reglas seleccionadas de axe (etiquetas, nombres de botones y
  contraste). Se revisaron capturas de escritorio claro y móvil oscuro.
- 20 casos de regresión existentes de `flows.spec.js` y `audit.spec.js`
  aprobados en la ejecución conjunta; los dos casos de configuración de cámaras
  que requerían corregir selectores del test se verificaron después dentro de
  la ejecución final de ocho casos. No hubo cambios funcionales entre esos recorridos.

Todas las pruebas usaron una base sintética aislada, eliminada al finalizar.
El vídeo de prueba procede de un `MediaStream` de canvas, sin solicitar una cámara
física. Los logs quedan en `backend/storage/webcam-phase-one-*.log` y las capturas
en `test-results/cameras-webcam-{desktop,mobile}-{light,dark}.png`.

La prueba física con la laptop requiere realizar los pasos anteriores en el
navegador del usuario. No se valida todavía el DVR, grabación de vídeo ni
compatibilidad de un endpoint HLS real. Las pruebas seleccionadas no certifican
accesibilidad ni preparación completa para producción.

## Continuación prevista

1. Grabación de prueba, límites de duración y confirmación de guardado privado.
2. Biblioteca real por día y hora, reproducción del archivo guardado y huecos.
3. Recorte y exportación compatible, descarga y compartir archivo cuando se admita.
4. Adaptador del DVR: confirmar modelo/firmware, un canal y un archivo histórico;
   luego cuatro canales, continuidad y operación con el cliente.

La webcam no valida las funciones del DVR ni sustituye una grabación continua.
