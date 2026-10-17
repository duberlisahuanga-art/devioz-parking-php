# Activar y desactivar usuarios

En Administración → Usuarios, abrir “Desactivar usuario” o “Activar usuario” y
confirmar. La cuenta propia muestra “Tu cuenta” sin control de cambio de estado.
La acción conserva los filtros y la página del listado tras guardar.

Sólo administradores activos pueden ejecutar la operación. El punto de entrada
usa POST y CSRF; GET sólo consulta. El servidor valida el ID y admite únicamente
active/inactive. No permite cambiar el rol, borrar cuentas ni desactivar la cuenta
propia. Las decisiones bloquean administradores en orden de ID, revalidan el actor
y bloquean el destinatario antes de guardar; se conserva al menos un admin activo.

Cada cambio incrementa session_version, invalida recuperaciones pendientes y deja
auditoría con estado anterior/nuevo y actor. Todo ocurre en una transacción: si
falla auditoría, se revierte el estado, la versión y la invalidación de enlaces.
Repetir el mismo estado es idempotente y no crea otro evento.

Desactivar impide login y bloquea sesiones anteriores en su próxima petición al
servidor; una pantalla ya abierta puede permanecer visible hasta esa petición.
Reactivar requiere un login nuevo, sin revivir sesiones anteriores. Las contraseñas,
vehículos, reservas, ocupaciones, pagos y egresos se conservan. No se cancela una
reserva ni se libera una plaza por desactivar una cuenta; administración sigue
pudiendo gestionar las operaciones existentes.

No requiere otra migración: utiliza status y session_version ya presentes, junto
con password_resets del módulo de recuperación. No se cambiaron datos reales.

Validación del 16/10/2026: integración con 91 comprobaciones, incluidas permisos,
autodesactivación, reintentos, datos asociados, rollback y dos procesos de admins
intentando desactivarse mutuamente (sólo una decisión aceptada y un admin activo).
Dos pruebas Playwright aprobadas en escritorio/móvil, temas claro/oscuro, CSRF,
revocación de sesión y login tras reactivación. Axe: reglas seleccionadas de
contraste y nombres de botones sin violaciones. Capturas users-status-*.png en
test-results. Calidad: lint, PHPUnit y PHPStan; advertencias de estilo visibles.

Las pruebas usaron bases nuevas devioz_test_* / devioz_browser_*. Se eliminó el
fixture temporal de navegador y se restituyó el archivo del fixture anterior.
