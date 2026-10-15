# Catálogo local de vehículos

Implementado el 15 de octubre de 2026 en la rama `mejora-ui-ux-v2`.

## Fuente y cobertura

Fuente: https://github.com/plowman/open-vehicle-db
Revisión fija: `2733dccf8b674ef2fc05106b944b03fb21e0f4ee`.
Los únicos archivos de datos copiados son `makes.csv` y `models.csv`, en
`database/import/open-vehicle-db/`. No hay consultas a GitHub desde la aplicación.

| CSV | Columnas utilizadas | Clave de origen |
| --- | --- | --- |
| makes.csv | make_slug, make_id, make_name | make_slug |
| models.csv | make_slug, model_slug, model_name, model_id, vehicle_type | (make_slug, model_slug) |

`models.make_slug` enlaza con `makes.make_slug`. La importación resuelve esa
relación a una FK local `make_id`. Los IDs de origen se conservan como información
de procedencia; la identidad se basa en los slugs, tal como indica el repositorio.
No se copian `first_year`, `last_year` ni `years`: no hay búsqueda por año y los
rangos comprimidos pueden tener huecos. `styles.csv` contiene versiones y se
relaciona también por marca/modelo; no es necesario para este formulario.
`orphaned_styles.csv` tampoco se importa.

El snapshot tiene 70 marcas y 1.678 modelos: 877 `car`, 638 `mpv` y 163 `truck`.
La opción Auto del formulario ofrece este catálogo de cuatro ruedas, conservando
el tipo original en `source_vehicle_type`. No se filtra por país ni se promete
cobertura completa del parque peruano. No hay filas de motocicletas: Moto usa
exclusivamente escritura manual. El importador omite y cuenta tipos distintos
de `car`, `mpv`, `truck` si un CSV futuro los incorpora.

SHA256 de los archivos descargados:

- makes.csv: `0d32e1bf7190b7739741256b56e640da28828c973bbeb6643d86657774345d61`
- models.csv: `038cf9dbd004d966c5b6159b22bbddd19f40f6b2437b49037585d224ebb76a3c`

## Instalación y reimportación

Requiere las tablas existentes de DEVIOZ y MariaDB disponible. Usar los ajustes
DB_HOST/DB_PORT/DB_DATABASE habituales. El ejecutor de migraciones admite
DB_MIGRATION_USERNAME y DB_MIGRATION_PASSWORD; el importador utiliza el usuario
normal de aplicación, con SELECT/INSERT/UPDATE en las tablas nuevas.

Desde la raíz, en PowerShell:

```powershell
& C:\xampp\php\php.exe tools\migrate.php
& C:\xampp\php\php.exe tools\import-vehicle-catalog.php
```

La migración versionada `database/migrations/20261015_vehicle_catalog.sql` crea
solamente `vehicle_catalog_makes` y `vehicle_catalog_models`, con utf8mb4, FK
restrictiva e índices por estado/nombre y marca/estado/nombre. No modifica
`vehicles`, usuarios, reservas ni pagos. El DDL tiene commit implícito en MariaDB:
no se presenta como reversible mediante rollback. El ejecutor existente registra
la migración y su checksum en `schema_migrations`.

El importador es CLI, valida cabeceras/filas y carga ambos CSV en una transacción.
Los slugs únicos evitan duplicados; una repetición actualiza los datos del catálogo
y reporta inserciones nuevas. No borra registros ausentes de una versión posterior
ni cambia `is_active` de registros existentes. Un CSV inválido revierte toda su
carga. No crea ni altera tablas durante la importación.

Para importar otro directorio local:

```powershell
& C:\xampp\php\php.exe tools\import-vehicle-catalog.php --csv-dir=C:\ruta\csv
```

Para una actualización, conservar primero los CSV y un respaldo de las dos tablas
del catálogo. Retirar JS/CSS no desactiva las validaciones de servidor; la entrada
de datos no listados requiere la casilla manual incluso sin JavaScript.
Para recuperar contenido del catálogo, restaurar su respaldo por separado. No
es necesario borrar ni cambiar vehículos históricos. La instalación inicial
no reemplaza datos existentes.

## Endpoint y comportamiento

Se usa un endpoint único en lugar de dos archivos equivalentes:

- GET `frontend/api/vehicle-catalog.php?kind=makes&type=car&q=Toy`
- GET `frontend/api/vehicle-catalog.php?kind=models&type=car&make_id=ID&q=Cor`

Requiere sesión activa mediante `require_auth()`. Responde JSON sin caché, 401
sin sesión, 422 para parámetros inválidos, 405 para otros métodos y 503 genérico
si falla el catálogo. Usa PDO con parámetros, búsqueda por prefijo insensible a
mayúsculas según la collation e interpretación literal de `%`, `_` y `!`.
Devuelve como máximo 15 resultados (el modelo PHP limita a 20 cualquier petición
interna). Para Moto devuelve lista vacía. No devuelve datos de usuarios.

El formulario real `frontend/conductor/vehiculos.php` carga JS/CSS propios del
componente. Debounce de 250 ms, AbortController y secuencia de solicitudes evitan
resultados antiguos. Marca seleccionada limita modelos por su ID; editar Marca
limpia Modelo y cambiar Tipo limpia ambos campos. Sin marca seleccionada el
modelo sigue siendo editable y explica cómo obtener sugerencias.

Los campos siguen enviando texto al controlador existente. Marca y modelo son
obligatorios. Para Auto se comprueba la pareja por sus nombres en servidor, sin
confiar en IDs o selecciones de JavaScript. Las parejas conocidas se guardan con
la escritura del catálogo. Se rechazan una marca escrita como modelo (Toyota/Kia)
y un modelo conocido de otra marca (Toyota/Sportage), aun con entrada manual.
Si la marca o el modelo no aparece, se debe marcar «Mi vehículo no aparece en
el catálogo». Moto conserva entrada manual sin exigir esa casilla, al carecer
de datos de motos. No se agrega FK a `vehicles`. La validación requiere que el
catálogo local esté disponible; una caída del endpoint AJAX no impide escribir.

Las sugerencias priorizan Toyota, Kia, Hyundai, Chevrolet, Suzuki, Nissan,
Mitsubishi, Mazda, Honda, Ford, Renault y Peugeot si están en la base local.
Es una preferencia de interfaz entre marcas del catálogo, no un ranking completo
del parque peruano ni un filtro que elimine marcas. Referencia de mercado:
[informe AAP de julio de 2025](https://aap.org.pe/storage/estadisticas/2025/Julio.pdf).
Las otras marcas siguen apareciendo al buscar. No se inventaron modelos para
marcas peruanas ausentes del CSV.

Placa: nueva política de entrada de la aplicación de 1 a 7 caracteres, letras
ASCII/números y un guion opcional interior (el guion cuenta en el máximo).
Acepta F3V-802 y ABC1234; rechaza espacios interiores, símbolos, guiones repetidos
y entradas largas, sin truncarlas para hacerlas válidas. Se conserva la
normalización a mayúsculas y formato XXX-XXX para seis caracteres alfanuméricos,
así como el rechazo de placas equivalentes con/sin guion. No se presenta esta
regla configurable de la aplicación como validación legal del formato peruano.
Propiedad, CSRF y eliminación se conservan; los registros previos no se corrigen.

Las listas usan combobox/listbox, etiquetas asociadas, estados anunciados,
flechas, Enter, Escape, cierre al salir y selección con puntero. Los colores usan
las variables del tema existente. No se cambia el selector de tema ni estilos
globales. El formulario mantiene Bootstrap y los dos tipos originales.

## Archivos de esta mejora

Modificados: `frontend/conductor/vehiculos.php`, `tools/browser-fixture.php` y,
en el ajuste posterior, `backend/bootstrap.php`, `backend/controllers/VehicleController.php`,
el modelo/JS del catálogo y sus pruebas.

Creados:

- `database/migrations/20261015_vehicle_catalog.sql`
- `database/import/open-vehicle-db/makes.csv`
- `database/import/open-vehicle-db/models.csv`
- `backend/models/VehicleCatalog.php`
- `backend/services/VehicleCatalogImporter.php`
- `frontend/api/vehicle-catalog.php`
- `frontend/assets/js/vehicle-catalog.js`
- `frontend/assets/css/vehicle-catalog.css`
- `tools/import-vehicle-catalog.php`
- `tests/vehicle_catalog.php`
- `tests/browser/vehicle-catalog.spec.js`
- `docs/vehicle-catalog.md`

El fixture de navegador importa el catálogo solo en su base generada
`devioz_browser_*`. `tests/vehicle_catalog.php` crea y limpia exclusivamente una
base aleatoria `devioz_catalog_test_*`, donde verifica importación, idempotencia,
relaciones, límites, texto SQL hostil, rollback y registro manual de ambos tipos.
Las pruebas nunca usan `devioz_parking` como fixture.

## Evidencia de instalación local

Migración aplicada mediante `tools/migrate.php`. Primera importación: 70 marcas
y 1.678 modelos insertados, cero modelos omitidos. Segunda importación: cero
inserciones, totales conservados. No se registraron vehículos de prueba en la
base local de aplicación.

La revisión de Git inicial ya tenía numerosos cambios pendientes ajenos a esta
mejora. Se conservaron; no se ejecutó reset, checkout, stash, add, commit ni push.

## Verificación de entrega

- `php tests/vehicle_catalog.php`: 42 comprobaciones correctas en base aislada,
  incluyendo combinaciones incoherentes, confirmación manual, prioridad de marcas,
  placas alfanuméricas y duplicados con/sin guion.
- `php tests/integration.php`: 22 comprobaciones existentes correctas.
- `php tools/quality.php`: lint de 78 archivos, 11 casos de ingreso, 7 pruebas
  PHPUnit (13 aserciones), PHPStan sin errores y estilo sin errores bloqueantes.
  Permanecen advertencias de estilo ajenas a esta mejora.
- `php -l`: correcto para los siete PHP nuevos o modificados de esta mejora.
- PHPCS específico sobre modelo, importador, endpoint y comando nuevos: sin
  errores ni advertencias después de corregir su formato.
- Playwright: seis pruebas correctas en Chromium, escritorio 1440 px y móvil
  390 px; ambos temas, Toyota/Corolla/Corolla Cross, teclado, limpieza de campos,
  modelos restringidos a marca, registro manual Auto/Moto, parámetros inválidos,
  rechazo anónimo/método, caída del catálogo y descarte de respuestas antiguas.
  Incluye prioridad inicial Toyota/Kia/Hyundai, rechazo HTTP directo de Toyota/Kia
  y Toyota/Sportage aun con entrada manual, y registro de F3V-802 y NEW1234.
  Se comprueba también dropdown a 768 px y ampliación CSS al 200 %; esta última
  es una aproximación automatizada, no una revisión de zoom nativo en cada navegador.
- Axe: reglas seleccionadas de contraste, labels y ARIA del formulario sin
  violaciones en ambos temas. No equivale a una auditoría WCAG completa.
- Capturas revisadas de escritorio claro y móvil oscuro. Las cuatro combinaciones
  de tema/tamaño están en `test-results/catalog-{light|dark}-{desktop|mobile}.png`.
- `EXPLAIN`: búsqueda de marcas utiliza `idx_catalog_make_search` (range, una
  fila estimada para Toy); modelos utiliza `idx_catalog_model_search` (range,
  cinco filas estimadas para Toyota/Cor).
- `git diff --check`: sin errores de espacios. Git muestra avisos de conversión
  LF/CRLF; no se cambió la configuración ni se normalizaron archivos ajenos.
- `git status --short`: árbol con cambios anteriores más esta mejora, sin commit
  ni push. `frontend/conductor/vehiculos.php` queda modificado; los archivos nuevos
  aparecen sin seguimiento. `tools/browser-fixture.php` ya pertenecía a los
  archivos sin seguimiento del trabajo previo.

Para ejecutar navegador se utilizó Node portátil porque no había Node/npm en
PATH; se invocó directamente `node node_modules/@playwright/test/cli.js test
tests/browser/vehicle-catalog.spec.js` con PHP_BIN apuntando a XAMPP. No se
agregaron dependencias ni cambios de despliegue. Se limpió el fixture temporal
al terminar. La base local conserva únicamente el catálogo real importado.

Limitaciones: cobertura geográfica incompleta, ausencia de motos, búsqueda por
prefijo (no por palabras intermedias), 15 sugerencias por consulta y validación
visual realizada en Chromium. Las actualizaciones de CSV son un proceso explícito
de mantenimiento, no una sincronización automática. No se incorporaron OCR,
cámaras, reconocimiento, ML ni enlaces de catálogo obligatorios para vehículos.
