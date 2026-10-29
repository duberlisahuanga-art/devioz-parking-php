<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/layout.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$resetMode = $resetMode ?? false;
$error = null;
$message = null;
if ($resetMode && isset($_GET['token'])) {
    if (!is_string($_GET['token']) || preg_match('/^[a-f0-9]{64}$/D', $_GET['token']) !== 1) {
        unset($_SESSION['recovery_token']);
        $error = 'El enlace es inválido. Solicita uno nuevo.';
        http_response_code(400);
    } else {
        $_SESSION['recovery_token'] = $_GET['token'];
        redirect_to('/frontend/restablecer-password.php');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if ($resetMode) {
            $password = $_POST['password'] ?? null;
            $confirmation = $_POST['password_confirmation'] ?? null;
            if (!is_string($password) || !is_string($confirmation)) {
                throw new ValidationException('Completa ambos campos.');
            }
            PasswordRecoveryService::reset((string)($_SESSION['recovery_token'] ?? ''), $password, $confirmation);
            unset($_SESSION['recovery_token'], $_SESSION['user_id'], $_SESSION['session_version']);
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $message = 'Contraseña actualizada. Inicia sesión con tu nueva contraseña.';
        } else {
            $email = $_POST['email'] ?? null;
            if (!is_string($email)) {
                throw new ValidationException('Ingresa un correo válido.');
            }
            PasswordRecoveryService::request($email);
            $message = PasswordRecoveryService::MESSAGE;
        }
    } catch (Throwable $exception) {
        http_response_code($exception instanceof RateLimitException ? 429 : 400);
        $error = public_error_message($exception);
    }
}
$title = $resetMode ? 'Establecer contraseña nueva' : 'Recuperar contraseña';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Devioz Parking</title>
    <link rel="icon" type="image/png" href="<?= h(app_url('/frontend/assets/favicon.png?v=' . filemtime(__DIR__ . '/../assets/favicon.png'))) ?>">
    <script src="<?= h(app_url('/frontend/assets/js/theme.js')) ?>"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= h(app_url('/frontend/assets/css/styles.css')) ?>" rel="stylesheet">
</head>
<body>
<main class="container py-5">
    <div class="row justify-content-center"><div class="col-12 col-md-8 col-lg-6">
        <div class="panel-card">
            <p class="eyebrow text-secondary">DEVIOZ Parking</p>
            <h1 class="h3 mb-3"><?= h($title) ?></h1>
            <?php if ($error) :
                ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php
            endif; ?>
            <?php if ($message) :
                ?><div class="alert alert-success" role="status"><?= h($message) ?></div><?php
            endif; ?>
            <?php if (!$message) : ?>
            <form method="post" action="<?= h(app_url($resetMode ? '/frontend/restablecer-password.php' : '/frontend/recuperar-password.php')) ?>" class="vstack gap-3">
                <?= csrf_field() ?>
                <?php if ($resetMode) : ?>
                    <p>Elige una contraseña de entre 8 y 72 bytes. Los enlaces vencen a los 30 minutos y sólo se pueden usar una vez.</p>
                    <div><label class="form-label" for="recovery-password">Contraseña nueva</label>
                        <input class="form-control" type="password" name="password" id="recovery-password" autocomplete="new-password" required minlength="8" aria-describedby="password-help"></div>
                    <p class="small text-secondary" id="password-help">La longitud en bytes puede ser mayor al número de caracteres si usas tildes o emojis.</p>
                    <div><label class="form-label" for="recovery-confirmation">Confirmar contraseña</label>
                        <input class="form-control" type="password" name="password_confirmation" id="recovery-confirmation" autocomplete="new-password" required minlength="8"></div>
                    <button class="btn btn-primary" type="submit">Guardar contraseña</button>
                <?php else : ?>
                    <p>Ingresa el correo de tu cuenta. Si está activa, recibirás un enlace para elegir una contraseña nueva. Revisa también la carpeta de spam.</p>
                    <div><label class="form-label" for="recovery-email">Correo electrónico</label>
                        <input class="form-control" type="email" name="email" id="recovery-email" maxlength="150" autocomplete="email" required></div>
                    <button class="btn btn-primary" type="submit">Enviar enlace</button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="<?= h(app_url('/frontend/login.php')) ?>">Volver a iniciar sesión</a>
                <?php if ($resetMode) :
                    ?><a href="<?= h(app_url('/frontend/recuperar-password.php')) ?>">Solicitar otro enlace</a><?php
                endif; ?>
            </div>
        </div>
    </div></div>
</main>
</body>
</html>
