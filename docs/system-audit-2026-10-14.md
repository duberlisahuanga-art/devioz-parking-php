# Escaneo de estado de DEVIOZ PARKING

Fecha: 14 de octubre de 2026, America/Lima.
Entorno: aplicación local de XAMPP en `/devioz-parking-php`.

## Dictamen

La aplicación responde y tiene controles útiles, pero la configuración observada
y varios flujos requieren correcciones antes de publicarla. El resultado del
baseline es verde; eso no implica que los filtros, la concurrencia, los permisos
de archivos o la accesibilidad estén correctos.

Se identificaron 15 hallazgos: 4 de prioridad alta (P1), 9 de prioridad media (P2)
y 2 de prioridad baja (P3). La prioridad representa orden de corrección según
impacto y contexto; no es una puntuación CVSS ni una certificación.

## Alcance y método

- Skills utilizadas: devioz-php, devioz-database, devioz-ui, devioz-security y devioz-qa.
- Lectura de autenticación, permisos, reservas, pagos, finanzas, cámaras,
  notificaciones, modelos, esquema, migraciones y formularios principales.
- Ejecución de `C:\xampp\php\php.exe tools\quality.php`.
- Solicitudes anónimas HTTP locales, sin intentar autenticarse ni mostrar
  contenido de documentos financieros o credenciales.
- Consultas de lectura y comprobación de metadatos/integridad. No se crearon
  usuarios, reservas, pagos ni egresos. Las peticiones PHP pueden crear sesiones
  temporales y registros normales de acceso del servidor.
- Pruebas aisladas de PDO, bcrypt, fechas y asociación de etiquetas en el HTML
  renderizado. No se modificó código de ejecución ni configuración para corregir
  los hallazgos; el único archivo creado por este escaneo es este informe.

No se hicieron pruebas autenticadas de extremo a extremo, carga, carreras con
datos reales, reproducción visual en navegador, lector de pantalla, validación
de streaming real ni restauración de respaldo. Tampoco se auditó una instalación
de producción. Los riesgos inferidos del código se distinguen de los resultados
reproducidos. No se afirma cumplimiento completo de ASVS o WCAG.

## Evidencia de funcionamiento actual

| Comprobación | Resultado |
| --- | --- |
| Sintaxis PHP | 57 archivos revisados, sin errores |
| Manifests de skills | 5 válidos según el comprobador básico local |
| Política de ingreso | 11 casos pasan |
| Raíz del proyecto | HTTP 302 hacia la landing |
| Landing y login | HTTP 200 |
| Dashboard admin, finanzas y dashboard conductor sin sesión | HTTP 302 hacia login |
| CSS y app.js | HTTP 200 |
| MariaDB | 10.4.32, innodb_force_recovery=0 |
| Tablas de aplicación | 11, todas InnoDB |
| Tablas adicionales de finanzas/preferencias/notificaciones | Presentes |
| Tablas internas reparadas previamente | gtid_slave_pos, db y columns_priv: CHECK TABLE OK |
| Datos actuales | 3 usuarios, 40 plazas, 0 reservas, 0 pagos, 0 egresos |
| Conflictos consultados | 0 plazas con reservas duplicadas, 0 pagos activos duplicados, 0 propietarios incorrectos, 0 fechas invertidas |

Las comprobaciones de reservas y pagos operan sobre tablas actualmente vacías:
no prueban que el sistema impida inconsistencias bajo concurrencia.

Controles favorables observados: hashes de contraseña, regeneración de sesión
al acceder, HttpOnly y SameSite=Lax, CSRF en los puntos de entrada de los flujos
principales, permisos por rol/propiedad, consultas parametrizadas, bloqueo de
plaza al reservar y bloqueo de reserva al registrar ingreso/salida. Egresos y
cámaras integran auditoría dentro de sus transacciones. Las notificaciones
filtran por usuario y restringen las rutas de acción.

## Hallazgos

### 01 — P1: archivos internos accesibles por HTTP

**Confirmado:** solicitudes anónimas a `/.git/HEAD`, `/.git/index`,
`/database/sql/schema.sql` y `/database/sql/seeds.sql` devolvieron HTTP 200.
Las URLs son relativas a la base local del proyecto. No se descargaron objetos
Git para reconstruir el repositorio ni se publicaron datos de los archivos.

**Impacto:** un cliente que alcance este servidor puede obtener metadatos del
repositorio y archivos SQL. Si se publica esta misma configuración, esa exposición
se traslada al entorno público. No se afirma que el servidor sea accesible desde Internet.

**Corrección:** definir una raíz pública controlada o reglas equivalentes que
denieguen `.git`, SQL, documentación interna y respaldos; desplegar sólo artefactos
necesarios. Deshabilitar listados de directorios.

**Aceptación:** las rutas internas devuelven 403/404 sin contenido y la navegación
normal de la aplicación continúa funcionando.

### 02 — P1: comprobantes financieros descargables sin sesión

**Confirmado:** `/backend/uploads/comprobantes/` entrega un índice de directorio.
Un archivo existente de esa carpeta y otro de `/backend/uploads/egresos/`
devolvieron HTTP 200 sin cookies ni autenticación. No se muestran sus nombres o
contenido en este informe.

**Código:** `backend/controllers/FinanceController.php:157` construye URLs estáticas;
`frontend/admin/pagos.php:963` y `frontend/conductor/pagos.php:547` hacen lo mismo.
El `.htaccess` de egresos evita listado/ejecución de ciertas extensiones, pero no
controla la lectura de imágenes y PDF por identidad.

**Corrección:** almacenar fuera de la raíz web o bloquear el acceso estático y
servir por un endpoint que verifique sesión, rol y propiedad del movimiento.
Los nombres aleatorios no sustituyen ese control.

**Aceptación:** anónimo y conductor ajeno no obtienen el documento; propietario
y administrador autorizado sí. El listado está deshabilitado en ambas carpetas.

### 03 — P1: transiciones de pagos y cancelaciones no son atómicas

**Confirmado por código; carrera no ejecutada:** en
`backend/controllers/PaymentController.php:10`, `:95` y `:122` se lee y valida el
estado antes de comenzar la transacción. `Payment::setStatus()` en
`backend/models/Payment.php:333` actualiza por ID, sin condicionar el estado previo.
La base no tiene restricción que impida múltiples pagos pending/paid para una reserva.
La revisión de cancelaciones en `ReservationController.php:259` y `:294` usa
lecturas sin bloqueo antes de su transacción.

**Impacto posible:** dos sesiones o peticiones concurrentes pueden pasar la misma
validación, enviar pagos duplicados o sobrescribir una decisión. La transacción
por sí sola no hace atómica una comprobación realizada fuera de ella.

**Corrección:** leer/bloquear dentro de la transacción, comprobar estado vigente,
usar actualizaciones condicionales y revisar idempotencia y restricciones.
Mantener un orden de bloqueo coherente entre reserva, pago y plaza.

**Aceptación:** en base de prueba, peticiones con conexiones/sesiones distintas
no generan pagos activos duplicados ni decisiones contradictorias. No hacerlo
sobre la base operativa.

### 04 — P1: configuración de desarrollo no apta para publicar

**Confirmado:** `backend/config/conexion.php:8` usa root sin contraseña.
La respuesta HTTP y `php.ini` muestran display_errors=On, expose_php=On y
versiones del servidor. La cookie observada no incluye Secure en HTTP local;
`backend/auth/session.php:6` no configura ese atributo. El login muestra
credenciales de demostración (`frontend/login.php:72`). No se intentó usarlas.

**Corrección antes de desplegar:** secretos externos, usuario SQL de privilegios
mínimos, HTTPS, cookies Secure configuradas para producción, errores públicos
genéricos y registro privado, eliminación de demos y revisión de versiones soportadas.
No imponer cookies Secure sobre HTTP local sin separar entornos.

**Aceptación:** staging reproduce la configuración de producción, no muestra
trazas/demos y utiliza credenciales y transporte adecuados.

### 05 — P2: fallan las búsquedas de usuarios, pagos y auditoría

**Reproducido:** `User::all('prueba-auditoria')`,
`Payment::allForAdmin(['search' => 'prueba-auditoria'])` y
`AuditLog::all('', 'prueba-auditoria')` lanzan PDOException con SQLSTATE HY093.

**Causa:** se repite `:search` en `backend/models/User.php:43`,
`backend/models/Payment.php:170` y `backend/models/AuditLog.php:39`, mientras
`backend/config/conexion.php` deshabilita emulación de prepares.

**Corrección:** un placeholder único por posición o parámetros posicionales.
No habilitar emulación global para ocultar el problema. Añadir pruebas con
búsqueda vacía/no vacía y filtros combinados.

### 06 — P2: fechas de negocio inconsistentes por zona horaria

**Reproducido:** PHP usa Europe/Berlin; `ReservationController::today()` usa
America/Lima, pero `store()` en `ReservationController.php:70` usa la zona por
defecto y varias marcas horarias usan `date()`. Para el instante
2026-10-15 00:30 UTC, la fecha por defecto es 15/10 y en Lima es 14/10.
La sesión de MariaDB informa time_zone=SYSTEM, sin que se haya demostrado su
alineación con Lima.

**Impacto:** puede rechazarse una reserva válida para el día local y registrarse
horas inconsistentes entre aplicación y base de datos.

**Corrección:** adoptar una convención explícita para persistencia y negocio;
usar America/Lima para límites de mensualidad y configurar la conexión SQL
según esa convención. Probar cambio de día y fin de mes.

### 07 — P2: registro permite contraseñas truncadas por bcrypt

**Reproducido en proceso aislado:** dos contraseñas sintéticas con los mismos
primeros 72 bytes y distinto sufijo verifican contra el mismo hash en este PHP.
`AuthController.php:56` sólo establece mínimo de 8 caracteres, sin máximo;
`SettingsController` sí controla el límite de bytes al cambiar contraseña.

**Corrección:** unificar la política de registro/cambio/login para el algoritmo
elegido, contemplar bytes multibyte y rechazar entradas inválidas sin truncado
silencioso. Probar 71/72/73 bytes. No cambiar hashes históricos sin estrategia.

### 08 — P2: sin limitación de intentos identificada en la aplicación

**Observado por revisión:** login y registro no implementan contador, retraso ni
rate limiting en los archivos examinados. No se hizo fuerza bruta; tampoco se
auditó un proxy/WAF externo que pudiera cubrirlo.

**Corrección:** definir límites para acceso/registro, respuestas controladas y
observabilidad. Evitar bloqueos fáciles de abusar contra cuentas ajenas.

### 09 — P2: errores internos pueden llegar a mensajes públicos

**Confirmado por código:** `backend/auth/login.php:22`,
`backend/auth/register.php:29` y `frontend/includes/layout.php:804` muestran
`Throwable::getMessage()` mediante flash sin distinguir excepciones técnicas
de errores de validación. Los mensajes se escapan, pero eso no evita divulgación
de detalles internos. Las trazas de PHP también están habilitadas.

**Corrección:** mensajes de validación permitidos; para fallos inesperados,
identificador público y detalle sólo en logs privados. Verificar con una
excepción controlada en entorno de prueba.

### 10 — P2: auditoría posterior al commit puede informar un fallo engañoso

**Confirmado por código; fallo de auditoría no provocado:** pagos hacen commit
en `PaymentController.php:73`, `:106` y `:133`, y escriben auditoría después.
Reservas también utilizan ese patrón. Si el log falla, la operación de negocio
ya se confirmó, pero el punto de entrada puede mostrar error y provocar reintentos.

**Corrección:** definir la garantía de auditoría; integrarla en la transacción
cuando sea obligatoria o usar un mecanismo durable con política explícita.
Un fallo posterior no debe afirmar que se revirtió lo ya confirmado.

### 11 — P2: envío de pago no verifica el estado final de la reserva

**Confirmado por código; envío real no ejecutado:** `PaymentController::submit()`
verifica propietario y payment_status=unpaid, pero no rechaza explícitamente
reservas cancelled/expired. Cancelar no cambia necesariamente payment_status.
Ocultar un botón en la interfaz no protege la petición al servidor.

**Corrección:** acordar estados elegibles y validarlos dentro de la transacción.
Probar reserva cancelada, vencida y cancelación pendiente según la regla de negocio.

### 12 — P2: etiquetas y modal de autenticación incompletos

**Reproducido sobre HTML renderizado:** seis inputs de la landing y dos de
`frontend/login.php` no tienen label asociado por `for/id`, label envolvente ni
nombre accesible mediante ARIA. Referencias: `frontend/index.php:2916` y
`frontend/login.php:52`.

El modal `frontend/index.php:2791` no declara role=dialog ni aria-modal.
`openAuth()`/`closeAuth()` no gestionan foco inicial, contención o retorno.
Existe cierre por Escape; el comportamiento de teclado no se probó en navegador.

Contrastes de dos pares concretos: blanco/azul #2563eb = 5.17:1 y
#64748b/blanco = 4.76:1. Ambos superan 4.5:1 para texto normal; esto no certifica
los contrastes de toda la UI ni los textos sobre imágenes.

**Corrección:** asociar etiquetas, describir el diálogo y gestionar foco;
verificar manualmente teclado, lector de pantalla, móvil, zoom y ambos temas.

### 13 — P2: cobertura automatizada insuficiente para una entrega comercial

**Confirmado:** sólo existe `tests/reservation_entry_policy.php`, con 11 casos
de una función de política. No se encontraron Composer, PHPUnit, PHPStan,
automatización de navegador ni workflows de CI en el repositorio.

**Impacto:** los errores PDO reproducidos no son detectados por el baseline.
No hay evidencia automatizada de autorización entre usuarios, flujos financieros,
subida/descarga de documentos, concurrencia o migraciones completas.

**Corrección:** priorizar pruebas de los hallazgos confirmados y de los flujos
críticos, base aislada y CI. Mantener el lint como comprobación básica.

### 14 — P3: cierre de sesión cambia estado mediante GET

**Confirmado por código:** `backend/auth/logout.php:7` invoca logout sin exigir
POST ni verificar CSRF. No se cerraron sesiones existentes durante este escaneo.

**Corrección:** formulario POST con token; definir cómo debe responder GET.
Impacto principal: cierre forzado de sesión, no toma de control de cuenta.

### 15 — P3: mantenimiento y crecimiento sin proceso reproducible completo

**Observado:** URL base fija en `backend/bootstrap.php:7`, migraciones SQL
separadas sin ejecutor/versionado aplicado registrado, cargas manuales del
bootstrap y listados con fetchAll sin paginación (usuarios, pagos, reservas,
auditoría). No se midieron problemas de rendimiento con el volumen actual.

**Corrección:** documentar/aplicar migraciones con historial y recuperación,
parametrizar despliegue y añadir paginación donde crezcan los datos. Introducir
autoload y herramientas de estilo de manera gradual, evitando reescribir por
completo el proyecto sólo para adoptar una estructura nueva.

## Orden recomendado de trabajo

1. Cerrar exposición de archivos internos y documentos privados; comprobar
   permisos de descarga con anónimo, propietario, conductor ajeno y admin.
2. Corregir placeholders PDO y unificar fechas; añadir pruebas que reproduzcan
   estos dos fallos antes de corregirlos.
3. Hacer atómicas las transiciones de pagos/cancelaciones y validar estados de
   pago elegibles; probar concurrencia en otra base.
4. Unificar contraseña, errores y auditoría; preparar configuración de producción
   y limitación de intentos.
5. Corregir formularios y foco; incorporar pruebas de integración, navegador y CI.
6. Verificar despliegue y restauración en staging y volver a escanear.

## Referencias

- [OWASP ASVS](https://owasp.org/projects/asvs): referencia para verificar controles
  de seguridad; no se completó su matriz de requisitos.
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/): referencia para etiquetas, foco y contraste;
  no se completó una auditoría de conformidad.
- [PDO::prepare](https://www.php.net/manual/en/pdo.prepare.php): restricciones de
  parámetros nombrados repetidos cuando se deshabilita emulación.
- [password_hash](https://www.php.net/manual/en/function.password-hash.php): límites
  del algoritmo bcrypt usado por PASSWORD_DEFAULT en el entorno revisado.
