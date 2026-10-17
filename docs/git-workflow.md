# Trabajo con Git y GitHub

Repositorio configurado: `https://github.com/duberlisahuanga-art/devioz-parking-php`.
Rama actual de trabajo: `mejora-ui-ux-v2`. Se publica el código en esa rama.
La publicación en GitHub no despliega automáticamente el sistema del cliente.

## Una mejora, un commit

1. Revisar el estado y actualizar las referencias del remoto.
2. Implementar una mejora con sus pruebas y documentación.
3. Ejecutar calidad y las pruebas pertinentes; usar bases aisladas para pruebas.
4. Preparar sólo los archivos o partes de archivos de esa mejora.
5. Revisar lo preparado, crear el commit y subirlo sin forzar.
6. Comprobar que el remoto contiene el commit y que el estado local es el esperado.

Ejemplo desde PowerShell, en la raíz del proyecto:

```powershell
git status
git fetch origin
git log --oneline --decorate -10
git diff
& C:\xampp\php\php.exe tools\quality.php
git add -- ruta/del/archivo.php docs/documento.md
git diff --cached
git commit -m "Describir la mejora concreta"
git push
git status
```

Las rutas del ejemplo se sustituyen por los archivos de la mejora. No usar
`git add .` sin revisar antes todos los archivos. Cuando un archivo contiene
cambios de varias mejoras, seleccionar partes con `git add -p` o preparar un
parche revisado para el índice.

## Comandos habituales

| Comando | Uso |
| --- | --- |
| `git status` | Ver rama, archivos pendientes y relación con el remoto. |
| `git diff` | Revisar cambios todavía no preparados. |
| `git add -- archivo` | Preparar archivos concretos para el próximo commit. |
| `git diff --cached` | Revisar exactamente lo que guardará el commit. |
| `git commit -m "mensaje"` | Guardar una versión local con su descripción. |
| `git log --oneline --decorate -10` | Consultar las versiones recientes. |
| `git fetch origin` | Actualizar referencias remotas sin cambiar el trabajo local. |
| `git push` | Enviar los commits de la rama al remoto configurado. |
| `git switch nombre-rama` | Cambiar a otra rama cuando el estado del trabajo lo permita. |
| `git merge nombre-rama` | Integrar una rama revisada en la rama actual. |

El comando correcto para fusionar es `git merge`. No hace falta usarlo después
de cada mejora. Si la rama remota avanzó, revisar los cambios antes de integrar;
si hay conflictos, resolverlos y repetir las comprobaciones antes de publicar.
No usar `push --force` para superar una diferencia de historial.

## Datos que requieren respaldo separado

Git guarda código, migraciones, pruebas y documentación. La base MariaDB,
cuentas de conexión del motor, credenciales SMTP, configuración privada,
comprobantes y vídeos se recuperan mediante procedimientos de respaldo propios.
No se suben al repositorio para intentar resolver fallos del motor.

Para iniciar el entorno local se necesitan Apache y MySQL activos en XAMPP.
Consultar `docs/local-db-connection-2026-10-16.md` para diagnóstico y recuperación
de la cuenta local. Los secretos siguen fuera del historial; nunca se deben
incluir en mensajes de commit, URLs de remotos o comandos compartidos.
