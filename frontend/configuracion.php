<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$user = require_auth();
$tabs = ['profile' => ['Perfil', 'bi-person', 'Actualiza tus datos personales.'],
    'security' => ['Seguridad', 'bi-shield-lock', 'Protege el acceso a tu cuenta.'],
    'appearance' => ['Apariencia', 'bi-palette', 'Elige cómo visualizar Devioz Parking.'],
    'notifications' => ['Notificaciones', 'bi-bell', 'Decide qué avisos recibir.'],
    'account' => ['Cuenta', 'bi-person-badge', 'Información de tu cuenta.']];
$activeTab = is_string($_GET['tab'] ?? null) && isset($tabs[$_GET['tab']]) ? $_GET['tab'] : 'profile';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $target = ['profile' => 'profile', 'password' => 'security', 'preferences' => 'notifications'][$action] ?? 'profile';
    run_post_action(function () use ($action): void {
        if ($action === 'profile') SettingsController::profile($_POST);
        elseif ($action === 'password') SettingsController::password($_POST);
        elseif ($action === 'preferences') SettingsController::preferences($_POST);
        else throw new RuntimeException('Acción no válida.');
    }, 'Cambios guardados correctamente.', '/frontend/configuracion.php?tab=' . $target);
}
$preferences = UserPreference::forUser((int) $user['id']);
$storedPhone = (string) ($user['phone'] ?? '');
$profilePhone = $storedPhone;
// Format an existing national number for display; keep the database unchanged.
if ($storedPhone !== '' && preg_match('/^[0-9]{9}$/D', $storedPhone) !== 1) {
    $digits = preg_replace('/[^0-9]/', '', $storedPhone) ?? '';
    if (preg_match('/^\+51[0-9 -]+$/D', $storedPhone) === 1 && strlen($digits) === 11) $digits = substr($digits, 2);
    $profilePhone = preg_match('/^[+0-9 -]+$/D', $storedPhone) === 1 && strlen($digits) === 9 ? $digits : '';
}
$isAdmin = $user['role'] === 'admin';
$labels = [
    'reservations' => [$isAdmin ? 'Nuevas reservas' : 'Estado de mi reserva', $isAdmin ? 'Mensualidades registradas por conductores.' : 'Avisos de ingreso y salida de tu reserva.'],
    'payments' => ['Pagos y comprobantes', $isAdmin ? 'Comprobantes enviados para revisión.' : 'Aprobación o rechazo de tus pagos.'],
    'pending_actions' => ['Solicitudes de cancelación', $isAdmin ? 'Solicitudes que requieren revisión.' : 'Resultado de tu solicitud de cancelación.'],
    'system_notices' => ['Seguridad de la cuenta', 'Avisos de cambios de contraseña de tu cuenta.'],
];
render_page_start('Configuración', 'configuracion');
?>
<div class="account-settings" data-settings-module>
    <div class="account-settings-intro"><h2>Ajustes de cuenta</h2><p>Administra tu perfil, seguridad, apariencia y preferencias de notificación.</p></div>
    <div class="account-settings-shell">
        <nav class="account-settings-nav" role="tablist" aria-label="Ajustes de cuenta">
            <?php foreach ($tabs as $key => [$label, $icon, $description]): ?>
            <button type="button" role="tab" id="settings-tab-<?= h($key) ?>" aria-controls="settings-panel-<?= h($key) ?>" aria-selected="<?= $activeTab === $key ? 'true' : 'false' ?>" tabindex="<?= $activeTab === $key ? '0' : '-1' ?>" data-settings-tab="<?= h($key) ?>"><i class="bi <?= h($icon) ?>" aria-hidden="true"></i><span><?= h($label) ?></span></button>
            <?php endforeach; ?>
        </nav>
        <div class="account-settings-card">
            <?php foreach ($tabs as $key => [$label, $icon, $description]): ?>
            <section role="tabpanel" id="settings-panel-<?= h($key) ?>" aria-labelledby="settings-tab-<?= h($key) ?>" data-settings-panel="<?= h($key) ?>" <?= $activeTab === $key ? '' : 'hidden' ?>>
                <header class="account-settings-head"><h3><?= h($label) ?></h3><p><?= h($description) ?></p></header>
                <div class="account-settings-body">
                <?php if ($key === 'profile'): ?>
                    <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
                        <div class="account-settings-fields">
                            <div class="account-settings-full"><label for="profile-name">Nombre completo</label><input class="form-control" id="profile-name" name="name" value="<?= h($user['name']) ?>" maxlength="150" autocomplete="name" required></div>
                            <div><label for="profile-email">Correo electrónico</label><input class="form-control" id="profile-email" name="email" type="email" value="<?= h($user['email']) ?>" maxlength="150" autocomplete="email" required></div>
                            <div><label for="profile-phone">Número de celular</label><input class="form-control" id="profile-phone" name="phone" type="tel" inputmode="numeric" maxlength="9" pattern="[0-9]{9}" value="<?= h($profilePhone) ?>" autocomplete="tel-national" aria-describedby="phone-help" data-settings-phone><small id="phone-help">Opcional. Si lo ingresas, usa exactamente 9 dígitos, sin +51 ni separadores.</small><?php if ($storedPhone !== $profilePhone): ?><small><?= $profilePhone !== '' ? 'Tu celular se muestra en formato nacional. Se actualizará al guardar.' : 'El celular anterior tiene un formato inválido. Ingresa uno válido; si guardas el campo vacío, se retirará del perfil.' ?></small><?php endif; ?></div>
                        </div>
                        <div class="account-settings-actions"><button class="btn btn-primary" type="submit">Guardar cambios</button></div>
                    </form>
                <?php elseif ($key === 'security'): ?>
                    <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="password">
                        <div class="account-settings-fields">
                            <div class="account-settings-full"><label for="current-password">Contraseña actual</label><input class="form-control" id="current-password" name="current_password" type="password" autocomplete="current-password" required></div>
                            <div><label for="new-password">Nueva contraseña</label><input class="form-control" id="new-password" name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                            <div><label for="confirm-password">Confirmar nueva contraseña</label><input class="form-control" id="confirm-password" name="password_confirmation" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                        </div>
                        <p class="account-settings-note"><i class="bi bi-shield-check"></i> Usa al menos 8 caracteres. La contraseña actual se verifica antes de guardar el cambio.</p>
                        <div class="account-settings-actions"><button class="btn btn-primary" type="submit">Actualizar contraseña</button></div>
                    </form>
                <?php elseif ($key === 'appearance'): ?>
                    <div class="account-theme-options" aria-label="Preferencia de apariencia">
                        <?php foreach (['light' => ['Claro', 'Interfaz luminosa para uso diurno.'], 'dark' => ['Oscuro', 'Menor brillo para trabajar de noche.'], 'system' => ['Sistema', 'Sigue la preferencia del dispositivo.']] as $choice => [$title, $help]): ?>
                        <button type="button" class="account-theme-choice" data-theme-choice="<?= h($choice) ?>" aria-pressed="false"><span class="account-theme-preview account-theme-preview--<?= h($choice) ?>" aria-hidden="true"><span></span><span></span></span><strong><?= h($title) ?></strong><small><?= h($help) ?></small></button>
                        <?php endforeach; ?>
                    </div>
                    <p class="account-settings-note">La apariencia se guarda automáticamente en este navegador.</p>
                <?php elseif ($key === 'notifications'): ?>
                    <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="preferences">
                        <div class="account-preference-list">
                            <?php foreach ($labels as $preference => [$title, $help]): ?>
                            <label class="account-preference" for="preference-<?= h($preference) ?>"><span><strong><?= h($title) ?></strong><small><?= h($help) ?></small></span><span class="form-check form-switch"><input class="form-check-input" role="switch" type="checkbox" id="preference-<?= h($preference) ?>" name="<?= h($preference) ?>" value="1" <?= (int) $preferences[$preference] === 1 ? 'checked' : '' ?>></span></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="account-settings-note">Estas preferencias se aplican a los nuevos avisos. Los anteriores permanecen en tu centro de notificaciones.</p>
                        <div class="account-settings-actions"><button class="btn btn-primary" type="submit">Guardar preferencias</button></div>
                    </form>
                <?php else: ?>
                    <dl class="account-information">
                        <div><dt>Rol</dt><dd><?= h(role_label($user['role'])) ?></dd></div>
                        <div><dt>Estado</dt><dd><?= h(status_label($user['status'])) ?></dd></div>
                        <div><dt>Correo</dt><dd><?= h($user['email']) ?></dd></div>
                        <div><dt>Miembro desde</dt><dd><?= h(format_date($user['created_at'])) ?></dd></div>
                    </dl>
                <?php endif; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php render_page_end(); ?>
