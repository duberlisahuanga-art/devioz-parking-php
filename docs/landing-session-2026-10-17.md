# Sesión visible y salida desde la portada

Cuando el visitante ya ha iniciado sesión, la portada muestra **Ir a mi panel**.
El navegador conserva la cookie y el servidor valida la cuenta al atender la
petición. Volver a la portada no equivale a cerrar sesión. El historial de las
ramas de GitHub no determina si una sesión está abierta.

Con la opción elegida por el usuario, ahora la barra de la portada muestra
**Sesión iniciada: nombre del usuario**, **Ir a mi panel** y **Cerrar sesión**.
En móvil estas opciones aparecen al abrir el menú. El nombre se escapa para
HTML y conserva una presentación acotada en escritorio.

El botón de salida utiliza el endpoint existente con POST y CSRF. Después
de salir aparecen nuevamente **Iniciar sesión** y **Crear cuenta**; el panel
requiere autenticarse otra vez. Se conserva el funcionamiento actual de la
sesión, sin imponer un tiempo nuevo de cierre automático.

## Verificación

La prueba `tests/browser/landing-session.spec.js` comprueba para administrador
y conductor, en escritorio y móvil:

- Identidad visible y enlace al panel correspondiente.
- Salida mediante teclado desde la portada y retorno de las acciones de invitado.
- Rechazo de cierre mediante GET y de POST con CSRF inválido, manteniendo la
  sesión durante los intentos rechazados.
- Rechazo del acceso al panel después de cerrar sesión, incluso reutilizando
  una copia anterior de la cookie.
- Controles dentro de la pantalla y contraste de los nuevos elementos en ambos
  temas, mediante comprobaciones específicas con axe.

Se ejecuta sobre el fixture sintético del proyecto, nunca sobre las cuentas
operativas:

```powershell
$env:PHP_BIN = 'C:\xampp\php\php.exe'
node node_modules/@playwright/test/cli.js test tests/browser/landing-session.spec.js tests/browser/flows.spec.js --grep 'identidad visible|landing disponible|landing, etiquetas'
& C:\xampp\php\php.exe tools/quality.php
```

Si Node.js no está en PATH, usar el ejecutable portátil instalado localmente
en `tools/runtime`, sin incluir ese runtime en Git. Se necesita el fixture
preparado por `tools/browser-fixture.php create` y limpiar únicamente el fixture
que haya creado la propia ejecución.

Las capturas generadas en `test-results` son evidencia local y no se publican
en Git. La revisión de contraste de estos controles no es una auditoría completa
de accesibilidad de la portada.

Resultado del 17/10/2026: ocho pruebas de navegador correctas, incluidas las
regresiones de portada y del modal de login/registro. Se revisaron capturas de
escritorio y móvil. `tools/quality.php` terminó con código 0; conserva las
advertencias de estilo existentes en archivos ajenos a esta mejora.
