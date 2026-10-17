# Desarrollo de DEVIOZ PARKING

## Contexto

PHP 8.2+, PDO/MariaDB, Bootstrap 5 y JavaScript sin compilación. El código
funcional está en `backend/` y `frontend/`; el esquema y las migraciones en
`database/`; las pruebas en `tests/`. XAMPP sirve la aplicación desde
`http://localhost/devioz-parking-php/`. No introducir un framework ni cambiar
el despliegue como efecto secundario de una tarea.

## Selección de habilidades

Leer solamente las skills pertinentes antes de trabajar:

| Trabajo solicitado | Skill local |
| --- | --- |
| Lógica PHP o arquitectura del backend | `.agents/skills/devioz-php/SKILL.md` |
| Consultas, migraciones o integridad de datos | `.agents/skills/devioz-database/SKILL.md` |
| Landing, formularios o paneles | `.agents/skills/devioz-ui/SKILL.md` |
| Revisión de seguridad o cambios en autenticación, permisos y archivos | `.agents/skills/devioz-security/SKILL.md` |
| Pruebas, regresiones o preparación de una entrega | `.agents/skills/devioz-qa/SKILL.md` |

Estas habilidades son procedimientos, no agentes independientes ni una
autorización para desplegar, conectarse a terceros o alterar datos reales.

## Reglas compartidas

- Conservar cambios del usuario y limitar cada modificación al alcance pedido.
- Las preferencias vigentes de UI son azul `#2563eb`, hover `#1d4ed8`,
  superficies claras y consistencia entre landing, login, registro y paneles.
  Conservar el selector de tema existente. No rediseñar la marca por iniciativa propia.
- Revisar `backend/bootstrap.php`, los controladores y las tablas involucradas
  antes de cambiar un flujo. Validar en servidor; el frontend no concede permisos.
- Conservar la propiedad de vehículos/reservas, la exclusión de plazas ocupadas,
  y la política de ingreso: reserva confirmada, pago aprobado, periodo vigente
  en America/Lima y sin cancelación pendiente.
- Para SQL dinámico usar parámetros PDO y listas permitidas para identificadores.
  Los cambios acoplados de reserva, plaza y pago requieren transacciones.
- Escapar HTML con `h()` o `htmlspecialchars`; reutilizar permisos y CSRF.
- Las correcciones de datos deben estar expresamente dentro de la solicitud.
  Para migraciones que alteren datos existentes, establecer respaldo y recuperación;
  evitar ejecutar pruebas sobre `devioz_parking`.
- Antes de entregar código ejecutar `php tools/quality.php` (en XAMPP:
  `C:\xampp\php\php.exe tools\quality.php`). Para cambios visuales revisar
  además el navegador en escritorio y móvil cuando esté disponible; indicar
  claramente si esa revisión no pudo hacerse.
- Reportar qué cambió, qué se verificó y qué sigue pendiente. No llamar a la
  aplicación certificada, segura o lista para producción sólo porque pasó lint.

## Preparación de mercado

Consultar `docs/development-quality.md` para criterios de entrega, uso de
skills y trabajo pendiente de producción. Los estándares son objetivos de
verificación; su cumplimiento necesita evidencia por requisito.

## Trabajo con Git y GitHub

- El usuario autorizó trabajar con Git, crear un commit por cada mejora y subir
  los commits al repositorio GitHub configurado en `origin`.
- Revisar `git status`, `git diff` y `git log` antes de guardar cambios. Actualizar
  referencias con `git fetch origin` y comprobar diferencias antes de publicar.
- Agrupar cada mejora con su código, pruebas y documentación. Seleccionar archivos
  explícitamente con `git add`; separar los cambios de archivos compartidos cuando
  correspondan a mejoras distintas. Ejecutar las verificaciones pertinentes.
- Mantener la rama de trabajo existente salvo indicación del usuario. Usar
  `git push` sin forzar; usar `git merge` sólo cuando corresponda integrar trabajo
  revisado. No fusionar automáticamente con `main`, reescribir historial ni
  sobrescribir cambios ajenos para completar una subida.
- Mantener credenciales, configuración privada, respaldos y archivos operativos
  fuera de Git. El historial de código no sustituye el respaldo de MariaDB,
  comprobantes ni grabaciones de cámaras.
- Referencia práctica: `docs/git-workflow.md`.
