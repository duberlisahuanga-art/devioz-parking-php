# Cámaras en vivo y grabaciones

El módulo administrativo de cámaras tiene dos pestañas independientes:

- **Cámaras en vivo:** canales del DVR, vista individual o 2x2, prueba con la
  webcam y configuración de cámaras.
- **Grabaciones:** vídeos guardados, calendario por día, filtro por hora,
  paginación, reproducción y descarga. El selector de estacionamiento se puede
  cambiar sin salir de esta pestaña.

Las grabaciones disponibles corresponden a la cámara de este equipo. La consulta
del historial del DVR sigue pendiente; esta separación no incorpora ese acceso.

Al abrir Grabaciones se liberan las transmisiones en vivo y la webcam. Volver a
Cámaras en vivo recupera la vista anterior; la webcam requiere conectarla de nuevo.
Si se cambia de estacionamiento, se vuelve a la cuadrícula de sus canales.
Al salir de Grabaciones se pausa su vídeo para evitar reproducción oculta.

Una grabación en curso, un guardado pendiente o un fallo de carga impiden abandonar
la vista de captura hasta guardar o descartar el vídeo. El aviso recibe el foco.

Las pestañas admiten ratón, pantalla táctil y teclado. Las flechas, Inicio y Fin
mueven el foco; Enter o Espacio activan la pestaña. La pestaña de Grabaciones y
sus filtros se conservan al recargar mediante parámetros de la URL.

## Verificación

Las pruebas de navegador usan un entorno y una base sintética aislados:

```powershell
& tools/runtime/node-v22.23.3-win-x64/node.exe tools/test-camera-webrtc.cjs --regression
& tools/runtime/node-v22.23.3-win-x64/node.exe tools/test-camera-webrtc.cjs
```

Se comprueban separación de paneles, teclado, liberación de vídeo, protección de
grabaciones pendientes, filtros, recarga, reproducción, permisos y diseño en
escritorio/móvil con temas claro y oscuro. La señal WebRTC de las pruebas es
sintética; no demuestra conexión con el DVR físico ni con un teléfono real.
