# Crear administradores desde Usuarios

En **Administrador → Usuarios → Nuevo administrador** se ingresa nombre, correo,
contraseña y confirmación. La nueva cuenta queda activa con rol `admin` y acceso
a los mismos módulos que el administrador que la creó. Usa el login habitual.
El administrador que completa el formulario conserva su sesión.

El formulario reutiliza los estilos, temas y controles existentes. Ante un error
se conserva nombre y correo; ambas contraseñas se vacían. El correo debe ser
único y se normaliza a minúsculas. La contraseña sigue la política existente.

## Controles del servidor

- Sólo un administrador autenticado puede consultar o enviar el formulario.
- La escritura exige POST y CSRF válido.
- El rol `admin` y el estado activo se fijan en el servidor.
- Se verifica nuevamente el rol, estado y versión de sesión del creador dentro
  de la transacción, con bloqueo de su cuenta.
- El registro de la cuenta y su auditoría se confirman juntos. Si falla la
  auditoría, se revierte la cuenta nueva.
- El evento **Administrador creado** identifica al creador y a la cuenta creada.
  No incluye la contraseña ni su hash en los metadatos.
- El índice único de correo evita cuentas duplicadas incluso entre peticiones
  simultáneas; el error se convierte en un mensaje comprensible.
- El registro público continúa creando exclusivamente conductores.

No requiere migraciones ni cambios de permisos SQL o credenciales locales.
La creación sólo afecta cuentas reales cuando el administrador usa el formulario;
las pruebas de esta entrega crearon únicamente cuentas sintéticas en bases aisladas.

## Verificación del 20/10/2026

- `php tests/administrator_creation.php`: 21 comprobaciones. Validaciones,
  contraseña verificada contra su hash, identidad del creador, auditoría sin
  secretos, sesión revocada, rollback y creación simultánea desde dos procesos.
- `php tests/integration.php`: 110 comprobaciones de regresión correctas.
- Chromium, escritorio y móvil: ocho pruebas correctas de creación, permisos,
  CSRF, duplicados, registro público y regresión de activar/desactivar usuarios.
  El nuevo administrador inició sesión, obtuvo el mismo menú y accedió a todos
  sus módulos, configuración y al formulario para crear otro administrador.
- Capturas de escritorio y móvil revisadas; ambos temas y comprobaciones axe
  de labels, nombres de botones/enlaces y contraste del formulario.
- `php tools/quality.php`: lint, PHPUnit, PHPStan y estilo con código de salida 0.
  Se mantienen las advertencias de estilo existentes en archivos ajenos.

Los fixtures de navegador se crearon y eliminaron para esta ejecución; se
conservó y restableció la referencia al fixture anterior. Capturas, registros,
contraseñas, configuración privada y bases de prueba no se publican en Git.
