---
name: devioz-security
description: Revisar seguridad de DEVIOZ o modificar autenticación, autorización, CSRF y cargas de archivos con evidencia y prioridades de corrección.
---

# Seguridad de DEVIOZ

Definir el flujo y activos afectados: cuentas, reservas, pagos, comprobantes,
finanzas y cámaras. Leer el punto de entrada además del controlador; algunos
controles se realizan fuera del método de negocio.

Comprobar autenticación, rol y propiedad en servidor para lecturas y escrituras.
Para cambios de estado comprobar método HTTP y CSRF. Probar un conductor contra
recursos de otro y contra acciones admin; ocultar un botón no es autorización.

Revisar `backend/auth/session.php`, `backend/controllers/AuthController.php` y
`backend/config/conexion.php` si cambia acceso o preparación de producción.
Verificar hash de contraseñas, renovación de sesión, cookies bajo HTTPS,
limitación de intentos, usuario SQL de privilegios mínimos y errores públicos.
La configuración local root sin contraseña no es una configuración de producción.

Para archivos revisar límites, MIME real, nombres generados, rutas, permisos
de descarga y ejecución en el directorio de destino. Incluir comprobantes de
pagos y egresos; no confiar solamente en la extensión ni en `.htaccess` sin
comprobar la configuración del servidor objetivo.

Revisar PDO parametrizado, escaping contextual y secretos en código, logs y
repositorio. No copiar datos reales ni credenciales a informes. Pruebas de
seguridad activas sólo dentro de los sistemas y alcance autorizados.

Usar OWASP ASVS como referencia y registrar por hallazgo archivo/línea,
condición reproducible, impacto, prioridad y corrección. Diferenciar hallazgo
verificado de sospecha. No declarar cumplimiento ASVS completo sin una matriz
de requisitos y evidencia. Ejecutar las pruebas relevantes después de corregir.
