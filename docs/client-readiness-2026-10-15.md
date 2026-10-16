# Evaluación de preparación de entrega — 15/10/2026

## Dictamen

Puntuación orientativa: **7,5/10 para preparación de entrega**. Es una valoración
técnica del alcance inspeccionado, no una certificación ni un porcentaje de avance.
El núcleo permite preparar una demostración y un piloto supervisado. No recomendaría
firmar aún una aceptación definitiva que incluya videovigilancia y operación real
sin completar las verificaciones del servidor, recuperación integral y aceptación.

La ausencia de pruebas de cámaras reales está incluida en la nota. No es sólo
pendiente de probar: HLS requiere infraestructura y endpoints publicados; WebRTC
se ofrece en el formulario, pero el reproductor no implementa la conexión.

## Alcance y evidencia

Revisión de bootstrap, autenticación/sesiones/permisos, modelos y controladores
principales, reservas y renovación, pagos/comprobantes, finanzas, auditoría,
notificaciones, cámaras, configuración de despliegue, CI y tareas de Windows.
No se modificaron flujos ni datos operativos para esta evaluación.

Comprobaciones ejecutadas en esta revisión:

- Calidad: lint de 94 archivos; 11 casos de ingreso; PHPUnit 7 pruebas y 13
  aserciones; PHPStan nivel 5 aprobado; estilo sin errores bloqueantes, con
  advertencias visibles.
- Integración: 57 comprobaciones aprobadas en base aleatoria aislada.
- Vencimiento/renovación: 21 comprobaciones aprobadas, incluidas carreras.
- Filtros de reservas: 28 comprobaciones aprobadas.
- Catálogo/vehículos: 42 comprobaciones aprobadas.
- Dos aprobaciones concurrentes: una sola decisión; aprobar/rechazar en paralelo:
  estados consistentes. Ambas pruebas aprobadas con fixture recién creado.
- Respaldo SQL sintético restaurado en otra base y conteos comprobados.
- Tareas locales de reservas y auditoría presentes; último resultado consultado: 0.

Evidencia previa de esta misma sesión: 20 pruebas Playwright de auditoría y flujos
aprobadas con datos limpios, escritorio/móvil, temas y reglas seleccionadas de axe.
No se repitió aquí toda la batería de navegador ni se midió carga representativa.
Las cámaras reales, el servidor del cliente y la aceptación del cliente no están
verificados. Las pruebas de calidad no demuestran ausencia de vulnerabilidades.

## Estado por área

| Área | Estado observado | Límite principal |
| --- | --- | --- |
| Reservas y plazas | Alta, renovación, vigencia, cancelación, ingreso/salida y cierre automático, con reglas y transacciones | Prueba de operación diaria con el cliente y sus escenarios reales |
| Vehículos | Propiedad, catálogo, normalización y controles de placas | Confirmar política de placas y casos fuera de catálogo con el cliente |
| Pagos | Comprobante, revisión manual, decisión idempotente y permisos | Yape no se valida automáticamente con un proveedor; no ofrecerlo como integración automática |
| Finanzas | Ingresos/egresos, comprobantes y exportación CSV | Ampliar escenarios de conciliación y mejorar errores de formulario |
| Seguridad | Sesiones, roles, CSRF, hash, limitación de acceso, rutas protegidas y cuenta SQL limitada local | Comprobar la configuración efectiva en el servidor de entrega y revisar recuperación de cuentas |
| Auditoría | Identidad de eventos nuevos, filtros/detalle, rechazos independientes y respaldo | Simplificar campos técnicos; identidad antigua no reconstruible; copia física fuera del equipo pendiente |
| Interfaz | Estilo compartido, móvil y temas con pruebas seleccionadas | Revisión completa con usuarios y accesibilidad de todas las pantallas |
| Cámaras | Gestión de canales y reproductor HLS preparado | DVR/transcodificador/endpoints reales no probados; WebRTC no implementado |
| Operación | Tareas y respaldo de auditoría instalados localmente; plantilla de producción y migrador | No hay evidencia de respaldo diario integral de base + comprobantes + configuración recuperado en el equipo final |

## Antes de entregar definitivamente

1. **Cámaras y alcance de videovigilancia.** Configurar una fuente HTTP/HTTPS HLS
   publicada por infraestructura compatible con el DVR y validar cuatro canales,
   permisos de acceso al propio stream, compatibilidad, audio si existe,
   latencia y recuperación tras desconexión/reinicio. El permiso del panel no
   acredita que el endpoint de vídeo esté protegido. Verificar si el endpoint
   ya lo proporciona el cliente; no presuponer que hay que desarrollar un servidor
   de vídeo. Si se contrata WebRTC, implementar signaling/reproducción; si no,
   retirar o deshabilitar esa opción para no prometer algo inexistente.
   Brillo/contraste/OSD actuales afectan al visor, no configuran físicamente el DVR.
   Evidencia: frontend/assets/js/app.js:2269 y frontend/admin/camaras.php:96–113.

2. **Instalación en el equipo o servidor final.** Probar instalación reproducible,
   esquema/migraciones, cuenta runtime limitada, secretos, rutas y tareas.
   Si se publica por web, validar HTTPS, cookies, bloqueo de carpetas y certificados
   efectivos; la plantilla con dominio de ejemplo no es un despliegue realizado.
   Si se entrega sólo para red local, documentar y probar ese alcance.
   Revisar dependencia de CDN y conectividad: Bootstrap, iconos y HLS se cargan
   externamente. Para operación sin Internet, servir esas dependencias localmente.

3. **Recuperación integral.** Automatizar respaldo de usuarios, vehículos, reservas,
   pagos, egresos y demás tablas, junto con comprobantes y configuración protegida.
   El respaldo diario instalado sólo cubre auditoría y contingencias. El Escritorio
   sigue en el mismo equipo. Añadir un destino físico independiente, gestionar las
   claves por separado y restaurar el conjunto en otro entorno, documentando
   frecuencia, pérdida máxima aceptable y tiempo de recuperación medido.

4. **Aceptación funcional con el cliente.** Ejecutar reserva → comprobante →
   aprobación → ingreso → salida → vencimiento/renovación; probar cancelación,
   rechazo, reintento, plaza ocupada, dos administradores y errores de red.
   Cotejar caja/CSV contra movimientos esperados y probar la carga prevista.
   Las carreras comprobadas no sustituyen una prueba de volumen sostenido.

5. **Versión y entrega operativa.** Preparar una versión identificable con los
   cambios actuales (hay cambios sin confirmar en Git), dependencias y migraciones;
   excluir secretos, backups, fixtures y herramientas de desarrollo del paquete
   público. Unificar documentación antigua que aún describe tareas ya realizadas.
   Entregar guía de instalación, uso, respaldos y recuperación, cuentas iniciales,
   capacitación, soporte y lista de limitaciones aceptadas.

## Mejoras e implementaciones según alcance contratado

- **Recuperación de contraseña:** no se encontró un flujo de “olvidé mi contraseña”
  con token y caducidad; el cambio actual exige conocer la contraseña anterior.
  Definir recuperación verificada o procedimiento de soporte antes de uso autónomo.
- **Gestión de usuarios:** la página administrativa consultada permite listar y
  filtrar, no activar/desactivar ni administrar cuentas desde esa pantalla.
  Implementarlo si el cliente debe gestionar acceso sin intervención técnica.
- **Claridad de auditoría:** usuario/acción/módulo/fechas como filtros principales;
  identificadores e IP en detalle o filtros avanzados. Es una recomendación todavía
  no aplicada; la pregunta previa del usuario no autorizaba un rediseño implícito.
- **Mensajes de egresos:** validaciones lanzan InvalidArgumentException, mientras
  public_error_message sólo permite mensajes de ValidationException. Un monto o
  fecha inválidos puede mostrar un error genérico en vez de explicar la corrección.
  Confirmado por lectura de FinanceController::validate y frontend/admin/finanzas.php;
  no se provocó un egreso inválido sobre la base operativa.
- **Configuración del negocio:** precio mensual y distribución de 40 plazas están
  fijados en código/vista. No bloquea al cliente actual si coinciden con su negocio;
  configurarlos desde panel si necesita modificarlos por sí mismo.
- **Servicios adicionales:** notificaciones observadas son internas; recuperación
  por correo, alertas externas, reembolsos, facturación e integración automática de
  pagos deben acordarse expresamente, no asumirse incluidos ni obligatorios.
- **QA y mantenimiento:** ampliar cobertura de cámaras/finanzas, ejecutar CI remoto
  y escaneo actualizado de dependencias antes de publicar, y ordenar advertencias
  de estilo sin ocultarlas. No se consultaron avisos externos de dependencias en
  esta evaluación; resultados históricos de escaneos no acreditan su estado actual.

## Criterio de cierre

Recomiendo entregar primero para demostración/piloto con límites explícitos y
cerrar la entrega definitiva cuando exista evidencia de cámaras contratadas,
instalación final, recuperación integral y aceptación del cliente. No hace falta
reescribir la aplicación ni introducir un framework para cerrar estos pendientes.
