---
name: devioz-ui
description: Modificar o revisar la interfaz de DEVIOZ, manteniendo la paleta azul compartida, accesibilidad y uso móvil en landing y paneles.
---

# UI/UX de DEVIOZ

Leer `frontend/assets/css/styles.css`, la plantilla afectada y, para paneles,
`frontend/includes/layout.php`. La landing y el login/registro modal viven en
`frontend/index.php`; existe además `frontend/login.php`. El tema se controla
en `frontend/assets/js/theme.js` con la preferencia `devioz_theme`.

Usar `--primary: #2563eb`, `--primary-dark: #1d4ed8` y las variables de superficie,
texto y borde existentes. Evitar una paleta alternativa en estilos inline.
Respetar las preferencias guardadas y ambos temas. Las superficies claras son
el modo inicial; no cambiarlo como efecto secundario de una edición visual.

Para un cambio de color identificar fondo y texto juntos, incluidos hover,
foco, selección, disabled y estados de validación. Medir contraste en los pares
afectados: objetivo WCAG 2.2 AA, 4.5:1 para texto normal y 3:1 para texto grande;
los componentes y sus estados también necesitan revisión. No inferir ausencia
de fatiga visual a partir de un tono de azul.

Los formularios necesitan labels asociados, errores comprensibles y manejo de
teclado. Los modales requieren foco de entrada, contención de foco, cierre por
Escape y retorno de foco. No reemplazar texto o iconos de estado sólo por color.

Revisar escritorio y móvil, desbordamiento horizontal, navegación por teclado,
zoom y ambos temas. Para landing comprobar también login y registro; para un
componente global revisar un panel admin y uno conductor. Si no hay navegador,
reportar esa limitación: HTTP 200 no verifica apariencia ni accesibilidad.

Usar capturas o referencias cuando estén disponibles y resumir resultados
observados, sin afirmar certificación de accesibilidad.
