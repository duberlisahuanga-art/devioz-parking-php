# Filtros de Reservas del administrador

Implementado el 15 de octubre de 2026 en `frontend/admin/reservas.php`.

## Uso

Buscar, Estado y Pago se muestran siempre. «Más filtros» contiene Plaza, Tipo de
vehículo, Vigencia, Fecha de inicio desde/hasta y Cancelación. Se abre al aplicar
cualquier filtro avanzado o al detectar un error de validación. Es un control
nativo de HTML, accesible por teclado, y no necesita JavaScript para funcionar.

- Buscar combina coincidencias de conductor/correo/placa y número exacto de
  reserva, con o sin `#`. La búsqueda de placa ignora el guion. `%` y `_` se
  buscan como texto literal; no actúan como comodines SQL.
- Estado depende de la pestaña: dentro/fuera en En curso, finalizada/cancelada
  en Historial. Cambiar de pestaña conserva el resto de filtros y elimina solo
  un estado incompatible; la paginación vuelve a la primera página.
- Pago distingue pagado, pendiente de aprobación y sin pagar. Tipo diferencia
  Auto/Moto usando el vehículo de la reserva, no el tipo de la plaza.
- Vigencia compara las fechas contratadas con el día actual en America/Lima:
  por comenzar si empieza después de hoy, vigente si incluye hoy, vencida si
  terminó antes. Es independiente del estado operativo o de cancelación.
  En curso + Vencidas permite identificar vehículos aún dentro tras vencer.
- El rango de fechas filtra exclusivamente `starts_on` e incluye ambos límites.
  Admite solo «desde», solo «hasta» o ambos. Fechas imposibles y rangos invertidos
  muestran un mensaje y no se convierten en una búsqueda sin restricciones.
- Plaza identifica un ID de plaza existente. Una plaza inexistente muestra error.
- Cancelación permite sin solicitud, pendiente, aprobada o rechazada.

Todos los filtros se combinan con AND. La búsqueda libre combina sus campos con
OR dentro de ese grupo. «Filtrar» aplica la selección y «Limpiar» restaura los
valores de la pestaña actual. Un cambio de selección sin pulsar Filtrar no altera
los resultados que se muestran.

## Paginación, contador y actualización

Se muestran hasta 50 coincidencias por página y se consulta una fila adicional
para determinar si hay una página siguiente. El contador usa COUNT sobre todas
las coincidencias de los filtros, incluso si hay varias páginas. Si una página
deja de existir, se ajusta a la última disponible.

La URL conserva los filtros validados. Los enlaces de paginación y las
redirecciones después de ingreso/salida/revisión conservan esa selección.
Cambiar de filtros o pestaña reinicia la página. Si una acción cambia el estado,
una fila puede desaparecer de la búsqueda porque ya no cumple el filtro.

El formulario está fuera de la región que actualiza `parking-live.js`. La
consulta automática sigue usando los filtros aplicados de la URL y no reemplaza
los campos ni cierra «Más filtros», incluso si hay cambios todavía sin aplicar.
La protección existente para notas de acciones y modales se conserva.

El cierre automático se ejecuta antes de filtrar y continúa procesando todas las
mensualidades. Ocultar una reserva mediante los filtros no evita que venza ni
que libere su plaza cuando corresponda. No se cambiaron las reglas de ingreso,
pago, propiedad, renovación ni cancelación.

## Implementación y seguridad

- `backend/models/ReservationFilters.php`: listas permitidas, fechas, límites y
  URL normalizada. Nunca se usan identificadores SQL enviados por el usuario.
- `Reservation::searchForAdmin()`: consultas PDO parametrizadas separadas para
  filas y total; placeholders únicos y orden estable por fecha/id.
- `ReservationController::adminData()`: exige administrador, mantiene el
  mantenimiento global y valida la existencia de la plaza.
- `Space::filterOptions()`: opciones de plaza para el formulario.
- `frontend/includes/reservation-filters.php`: formulario Bootstrap, etiquetas
  asociadas y mensajes escapados. Acceso directo sin contexto devuelve 404.

No se requieren migraciones ni cambios de datos operativos. Se conservaron los
cambios anteriores del repositorio, sin commit ni push.

## Verificación

`php tests/reservation_filters.php` ejecuta 28 comprobaciones en una base
aleatoria `devioz_filters_test_*`, eliminada al terminar: combinaciones, búsqueda,
fechas límite, 59 resultados paginados, comodines literales, SQL hostil, parámetros
inválidos, permisos y mantenimiento de reservas ocultas.

Las pruebas Playwright de `tests/browser/reservation-filters.spec.js` usan
`devioz_browser_*` y comprueban ambos temas, escritorio/móvil, contraste y labels
del formulario, filtros combinados, refresco conservando cambios sin aplicar,
acciones conservando filtros, historial, paginación, fechas inválidas y limpieza.
También se ejecuta la prueba del ciclo de vencimiento y renovación.

Resultado: las cuatro pruebas de navegador (filtros y ciclo de mensualidades,
en ambos tamaños) pasaron. Las reglas de contraste, labels y ARIA seleccionadas
no reportaron violaciones en el formulario; no equivalen a una auditoría WCAG
completa. Capturas revisadas en escritorio claro y móvil oscuro.
`tools/quality.php` y `git diff --check` correctos; permanecen las advertencias
de estilo previas.
