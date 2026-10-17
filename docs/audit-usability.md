# Auditoría: consulta simplificada

La vista principal muestra fecha, usuario, acción y descripción. La IP y el
registro relacionado se consultan al abrir el detalle del evento. Los nombres
de los campos modificados se presentan en español.

Búsqueda, acción, módulo y fechas permanecen visibles. Los identificadores
se agrupan en filtros avanzados, que se abren automáticamente cuando hay un
filtro avanzado aplicado. Limpiar filtros vuelve a la consulta inicial.

No se borran eventos ni se cambia su contenido, los permisos administrativos,
la paginación o la búsqueda por identidad histórica. Los registros antiguos
sin identidad conservada continúan indicándolo; no se atribuyen a otro usuario.

Validación: `php tools/quality.php` y
`playwright test tests/browser/audit.spec.js`. El escenario de navegador cubre
filtros básicos y avanzados, detalle con teclado, fechas inválidas, ambos temas,
contraste seleccionado y desbordamiento en escritorio y móvil con datos sintéticos.
