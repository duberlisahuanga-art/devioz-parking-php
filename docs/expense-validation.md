# Mensajes claros en egresos — 16/10/2026

Las validaciones esperadas de Finanzas ahora usan ValidationException. Los errores
de campos del egreso usan ExpenseValidationException con nombre de campo y mensaje
controlado; no se publican mensajes arbitrarios de excepciones internas.

Se informa cómo corregir monto vacío, cero/negativo, exceso de decimales o límite;
fecha/hora ausente o imposible; categoría y método no disponibles; concepto,
descripción y detalle de Otros ausentes; texto demasiado largo y comprobantes
inválidos o con carga incompleta. Se mantienen las reglas existentes de importes,
fechas y permisos de administrador, sin cambiar pagos ni balances.

El formulario se reabre con los datos de texto ingresados. El error aparece dentro
del modal, se marca el campo con aria-invalid/aria-describedby y se lleva el foco
al campo. Se indica volver a adjuntar archivos nuevos, que el navegador no puede
restaurar tras enviar. En edición se conserva el comprobante guardado cuando no
se selecciona uno nuevo. Abrir otro formulario limpia las marcas y avisos anteriores.

Los errores inesperados conservan su referencia pública y diagnóstico privado.
Se retiraron instrucciones de SQL/migraciones de los mensajes visibles al usuario.
Los filtros financieros también producen mensajes de validación controlados.

Verificación: 110 comprobaciones de integración en base aislada, con casos de
validación, comprobantes incompletos, creación válida y edición inválida que no
altera el importe. Dos pruebas de navegador aprobadas en escritorio y móvil,
incluyendo conservación de datos, foco, error dentro del modal, corrección y
edición posterior. Temas claro/oscuro y reglas seleccionadas de axe (etiquetas,
nombres de botones y contraste) sin violaciones en el modal examinado.
Capturas: test-results/expense-validation-*.png.
Calidad aprobada: lint de 107 archivos, PHPUnit, política de ingreso y PHPStan;
permanecen advertencias de estilo anteriores. No se crearon ni editaron egresos reales.
