<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$error = null;
$name = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    try {
        verify_csrf();
    } catch (Throwable $exception) {
        http_response_code(403);
        $error = public_error_message($exception);
    }
    if ($error === null) {
        try {
            AdminController::createAdministrator($_POST);
            flash('success', 'Administrador creado correctamente. Ya puede iniciar sesión con su correo y contraseña.');
            redirect_to('/frontend/admin/usuarios.php?' . http_build_query(['search' => strtolower($email)]));
        } catch (Throwable $exception) {
            http_response_code($exception instanceof ValidationException ? 422 : 500);
            $error = public_error_message($exception);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Método no permitido.');
}

render_page_start('Nuevo administrador', 'usuarios');
?>
<div class="row">
    <div class="col-lg-8 col-xl-6">
        <section class="panel-card" aria-labelledby="new-admin-heading">
            <h2 class="h5" id="new-admin-heading">Crear cuenta de administrador</h2>
            <p class="text-secondary">La nueva cuenta tendrá acceso completo a todos los módulos, igual que los demás administradores.</p>
            <?php if ($error !== null): ?>
                <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post" class="vstack gap-3" data-prevent-double-submit>
                <?= csrf_field() ?>
                <div>
                    <label class="form-label" for="admin-name">Nombre completo</label>
                    <input class="form-control" id="admin-name" name="name" value="<?= h($name) ?>" maxlength="150" autocomplete="name" required>
                </div>
                <div>
                    <label class="form-label" for="admin-email">Correo electrónico</label>
                    <input class="form-control" id="admin-email" type="email" name="email" value="<?= h($email) ?>" maxlength="150" autocomplete="email" required>
                </div>
                <div>
                    <label class="form-label" for="admin-password">Contraseña</label>
                    <div class="input-group">
                        <input class="form-control" id="admin-password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="admin-password-help" required>
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="#admin-password" aria-label="Mostrar contraseña"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                    <p class="form-text" id="admin-password-help">Mínimo 8 caracteres.</p>
                </div>
                <div>
                    <label class="form-label" for="admin-confirmation">Confirmar contraseña</label>
                    <input class="form-control" id="admin-confirmation" type="password" name="password_confirmation" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Crear administrador</button>
                    <a class="btn btn-outline-primary" href="<?= h(app_url('/frontend/admin/usuarios.php')) ?>">Cancelar</a>
                </div>
            </form>
        </section>
    </div>
</div>
<?php render_page_end(); ?>
