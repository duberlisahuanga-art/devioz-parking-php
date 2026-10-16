# Recuperación de contraseña por correo

Flujo: Olvidé mi contraseña → correo registrado → enlace → contraseña nueva →
login normal. Disponible desde el login independiente y el modal de la landing.
No inicia sesión automáticamente ni altera las cuentas al solicitar el enlace.

## Implementación

- PHPMailer por SMTP con TLS/SSL, sin depuración de credenciales.
- Respuesta pública uniforme para cuentas inexistentes/inactivas. El envío se
  procesa por tarea de Windows cada minuto, fuera de la petición web, evitando
  diferencias de respuesta por el tiempo de SMTP.
- Token aleatorio de 32 bytes, hash SHA-256 para validación y 30 minutos de vida
  desde la solicitud. Una solicitud nueva reemplaza la anterior.
- Token de la cola cifrado con AES-256-GCM y clave derivada de APP_SECRET;
  se elimina de la cola al entregar. No registrar enlaces, contraseñas ni tokens.
- Límites por IP y correo; hasta tres intentos de envío por solicitud. La cuenta
  no queda bloqueada por solicitar recuperación. Solicitar otro enlace si se
  agotaron los envíos o venció durante una interrupción.
- Cambio de contraseña, consumo del token, revocación de sesiones y auditoría
  dentro de la misma transacción. Locks en orden usuario → recuperación evitan
  que solicitudes/consumos simultáneos acepten tokens reemplazados o consumidos.
- session_version revoca las sesiones previas; las cuentas anteriores comienzan
  en versión 0, compatible con sus sesiones actuales. Cuentas inactivas, cambio
  de correo o cambio de contraseña desde otro flujo invalidan el enlace.
- El token se retira de la URL mediante redirección y se mantiene en sesión;
  no-store/no-referrer en recuperación. Configurar logs del servidor para no
  conservar parámetros sensibles de la primera URL del enlace.
- Aviso interno tras recuperar contraseña. No se envía la contraseña por correo.

## Gmail local

Remitente configurado únicamente en backend/config/local.php, ignorado por Git.
SMTP smtp.gmail.com, puerto 587, STARTTLS. Falta la contraseña de aplicación.
No se han enviado correos reales ni se ha comprobado aún su entrega en Gmail.

1. Activar verificación en dos pasos y crear una contraseña de aplicación de
   Gmail si la cuenta dispone de esa opción:
   https://support.google.com/accounts/answer/185833?hl=es
2. Desde PowerShell, en la raíz del proyecto, ejecutar:

   `powershell.exe -NoProfile -ExecutionPolicy Bypass -File tools/configure-smtp-secret.ps1`

   Introducir la contraseña de aplicación en la entrada oculta. No usar la
   contraseña habitual de Gmail, no enviarla por chat ni incluirla en commits.
3. Registrar o usar una cuenta DEVIOZ con un correo real accesible. No se sustituyen
   usuarios ficticios ni se asocia automáticamente el remitente a una cuenta.
4. Abrir http://localhost/devioz-parking-php/frontend/recuperar-password.php,
   solicitar enlace y esperar el procesamiento de la tarea (intervalo de un minuto;
   el proveedor puede tardar más). Revisar spam. Abrir desde este mismo equipo.
5. Cambiar contraseña y comprobar login nuevo y rechazo de enlace usado.

La URL base se fija en PASSWORD_RESET_BASE_URL; nunca se deriva del Host recibido.
El correo localhost sólo funciona en el equipo que sirve la app. Producción debe
usar URL HTTPS accesible y variables de entorno; local.php no se carga en producción.
Si Gmail no permite contraseñas de aplicación, configurar OAuth2 o un proveedor
SMTP compatible; el código actual usa autenticación SMTP con contraseña de aplicación.

Tarea: DEVIOZ-Parking-RecoveryMail. CLI manual: `php tools/recovery-mail-worker.php`.
Sin SMTP configurado no consume intentos. Errores de envío producen un aviso
controlado en el log PHP y aumentan delivery_attempts, sin exponer el error SMTP.
Si el proceso SMTP entrega pero falla el commit, un reintento puede duplicar el
correo con el mismo enlace, que sigue siendo de un solo uso.

## Datos, despliegue y recuperación

Migración aditiva: database/migrations/20261015_password_recovery.sql.
Agrega users.session_version y password_resets. No elimina ni cambia contraseñas.
El configurador local respaldó la base completa a backend/storage/recovery-migration,
la restauró en una base temporal, comparó conteos y luego aplicó la migración.
La cuenta de aplicación recibe CRUD únicamente sobre la tabla nueva; la restricción
de auditoría permanece. No se debe exponer password_resets en paneles ni exportaciones.

Para otra instalación: respaldar y probar restauración, ejecutar tools/migrate.php
con credenciales de migración y conceder permisos de tabla a runtime. Crear la tarea
de envío o cron equivalente. PHP requiere OpenSSL y Composer.
El configurador tools/configure-recovery-local.php es sólo para este XAMPP local;
no se deben aplicar sus valores por defecto a producción.

Rollback preferido: revertir código conservando columnas/tabla, detener la tarea y
eliminar permisos de recuperación si procede. No prometer rollback transaccional
del DDL. Restaurar la base completa sólo en otra base primero: sobrescribir la
operativa perdería movimientos posteriores. Si hubo recuperación, el código viejo
no aplica session_version; invalidar sesiones antes de volver a ese código.

Pruebas: tests/integration.php usa correos sintéticos y un callback capturador,
sin red. tests/browser/recovery.spec.js prueba el formulario y el cambio mediante
un token generado exclusivamente en una base devioz_browser_*.
La entrega real a Gmail queda pendiente hasta configurar su secreto privado.

Validación local: calidad aprobada (103 archivos PHP, PHPUnit, política de ingreso
y PHPStan), Composer validado y sin avisos conocidos en la consulta de instalación;
74 comprobaciones de integración aprobadas. Se aprobaron 18 casos existentes de
login/registro/permisos y las dos pruebas de recuperación escritorio/móvil; se
corrigió el contraste del texto de marca en modo oscuro detectado por axe.
La tarea de envío está instalada y devuelve 0 mientras espera configuración SMTP.
No se ejecutó entrega real ni se afirmó éxito de Gmail sin credenciales.
