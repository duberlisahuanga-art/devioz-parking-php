# Entorno de desarrollo y calidad de DEVIOZ

Actualización: las correcciones y herramientas posteriores al escaneo están
implementadas y documentadas en [hardening-2026-10-14.md](hardening-2026-10-14.md).
Las secciones de preparación siguientes describen la planificación inicial;
consultar ese documento para distinguir lo completado de lo pendiente en producción.

## Lo que queda implementado

`AGENTS.md` conserva las decisiones del proyecto y dirige al asistente hacia
cinco skills locales en `.agents/skills/`: PHP, datos, UI/UX, seguridad y QA.
Son instrucciones de trabajo especializadas; no crean cinco procesos de IA
ni garantizan que el sistema cumpla un estándar.

`tools/quality.php` ejecuta lint de los archivos PHP, comprueba la estructura
básica de las skills y ejecuta los 11 casos de la política de ingreso, PHPUnit,
PHPStan y estilo. Requiere `composer install`. No usa la base de datos ni levanta
servicios; integración y navegador tienen comandos separados. El chequeo acepta el
formato simple de estas skills; no es un parser completo de YAML ni reemplaza
la validación de paquetes complejos de terceros.

Desde la raíz en PowerShell:

```powershell
& C:\xampp\php\php.exe tools\quality.php
```

Si PHP está en PATH: `php tools/quality.php`.

## Uso de las skills

Ejemplos de solicitudes para Codex:

- `$devioz-php corrige este flujo de reservas y verifica los casos límite.`
- `$devioz-database revisa esta migración y su recuperación.`
- `$devioz-ui revisa login y registro con la paleta azul y teclado.`
- `$devioz-security audita los permisos de descarga de comprobantes.`
- `$devioz-qa prepara la evidencia de pruebas para esta entrega.`

Codex puede seleccionarlas automáticamente según la tarea. Están disponibles
en el siguiente turno; si el selector no se actualiza, reiniciar la sesión del
IDE. Mantener las skills junto al código permite revisarlas y versionarlas.
Referencia: https://learn.chatgpt.com/docs/build-skills

## Criterios de calidad

| Área | Referencia / criterio | Evidencia necesaria |
| --- | --- | --- |
| PHP | PSR-12 como convención de estilo | Revisión o formatter sobre código modificado |
| Seguridad | OWASP ASVS, requisitos seleccionados según riesgos | Matriz de requisitos, pruebas y hallazgos resueltos |
| UI/UX | WCAG 2.2 AA como objetivo | Contraste medido, teclado, foco, formularios y revisión responsive |
| Base de datos | Integridad, concurrencia y recuperación | Migraciones probadas, carreras verificadas y restauración demostrada |
| QA | Criterios de aceptación del producto | Casos positivos/negativos y regresiones con resultados reproducibles |
| Operación | Despliegue reproducible y observabilidad | Configuración, logs, alertas, respaldo y prueba de recuperación |

Referencias oficiales:

- https://www.php-fig.org/psr/psr-12/
- https://owasp.org/projects/asvs
- https://www.w3.org/TR/WCAG22/

No existe un único paquete de skills que certifique todas estas áreas.
Estilo, seguridad, accesibilidad y operación se verifican por separado.

## Preparación para producción: trabajo pendiente

Esto es una hoja de ruta, no una auditoría completa del código ni trabajo ya realizado.

1. Inventariar flujos críticos y criterios de aceptación: registro/login,
   reserva, pago, aprobación, entrada/salida, cancelación y finanzas.
2. Separar configuración local y producción. Actualmente
   `backend/config/conexion.php` contiene root sin contraseña y
   `backend/bootstrap.php` fija la URL base. Preparar secretos externos,
   usuario de base limitado y HTTPS para el entorno de despliegue.
3. Adoptar Composer y elegir versiones compatibles de PHPStan, PHPUnit y una
   herramienta de estilo. Establecer baseline de deuda explícito, sin ocultar
   nuevas regresiones. No están instaladas por esta configuración inicial.
4. Crear base de prueba separada y fixtures sintéticos. Añadir integración para
   permisos, SQL, finanzas y carreras de reservas/pagos. El test actual no los cubre.
5. Probar UI en navegador: autenticación, panel admin y conductor, móvil,
   teclado y ambos temas. Añadir automatización de los flujos estables.
6. Hacer revisión de seguridad con evidencia ASVS: autorización por recurso,
   sesiones, protección de archivos, errores y limitación de intentos.
7. Incorporar CI que ejecute comprobaciones en cada cambio. Agregar escaneo de
   dependencias cuando exista un lockfile y acordar criterios para bloquear entregas.
8. Validar despliegue en staging, restauración de respaldos y recuperación antes
   de publicar. Registrar fallos críticos abiertos y criterios de aceptación.

## Catálogos externos

El usuario confirmó el catálogo https://github.com/sickn33/agentic-awesome-skills,
antes conocido como `antigravity-awesome-skills`. Se revisaron `php-pro`,
`sql-pro`, `security-auditor` y `accessibility-compliance-accessibility-audit`.
Las tres primeras tienen metadata `risk: critical`; esa etiqueta del autor no
es una auditoría independiente. Las instrucciones de SQL y seguridad abarcan
muchos entornos que DEVIOZ no utiliza. Las cinco skills locales son propias,
específicas del proyecto; no se han instalado skills de terceros.

Para incorporar una habilidad externa: identificar el caso de uso, revisar el
SKILL.md, scripts, licencia y dependencias; registrar repositorio, ruta y commit;
instalar una selección acotada y probar una tarea representativa. Actualizar
de forma revisable. Un catálogo grande es una fuente de opciones, no una prueba
de calidad de cada habilidad. Evitar instrucciones que sustituyan los permisos
del usuario o envíen datos del proyecto a servicios no autorizados.
