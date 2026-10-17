<?php

declare(strict_types=1);

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $cameraId = (int) ($_POST['camera_id'] ?? 0);
    run_post_action(function () use ($action, $cameraId): void {
        if ($action === 'create') CameraController::store($_POST);
        elseif ($action === 'update') CameraController::update($cameraId, $_POST);
        elseif ($action === 'delete') {
            if (($_POST['confirm_delete'] ?? '') !== '1') throw new RuntimeException('Confirma la eliminación.');
            CameraController::destroy($cameraId);
        } else throw new RuntimeException('Acción no válida.');
    }, 'Cámara actualizada correctamente.', '/frontend/admin/camaras.php');
}

$cameras = CameraController::index();
$parkings = Parking::allWithStats();
render_page_start('Cámaras', 'camaras');
?>
<div class="camera-hmi" data-camera-module>
    <script type="application/json" data-camera-data><?= json_encode($cameras, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
    <header class="camera-hmi-heading">
        <div><span class="camera-hmi-eyebrow">VIDEOVIGILANCIA · DVR / 4 CANALES</span><h2>Centro de monitoreo</h2><p>Canales del DVR y prueba con la cámara de este equipo.</p></div>
        <label>Estacionamiento
            <select data-camera-parking><?php foreach ($parkings as $parking): ?><option value="<?= h($parking['id']) ?>"><?= h($parking['name']) ?></option><?php endforeach; ?></select>
        </label>
    </header>
    <div class="camera-hmi-toolbar">
        <button type="button" data-camera-action="grid"><i class="bi bi-grid"></i> Vista 2x2</button>
        <button type="button" data-camera-action="single"><i class="bi bi-square"></i> Vista individual</button>
        <button type="button" data-camera-action="webcam"><i class="bi bi-laptop" aria-hidden="true"></i> Cámara de este equipo</button>
        <button type="button" data-camera-action="audio" aria-pressed="false"><i class="bi bi-volume-mute"></i> Audio global: silenciado</button>
        <button type="button" data-camera-action="fullscreen"><i class="bi bi-arrows-fullscreen"></i> Pantalla completa</button>
        <button type="button" data-camera-action="manage"><i class="bi bi-sliders2"></i> Gestión / Configuración</button>
    </div>
    <p class="camera-hmi-message" data-camera-message role="status" aria-live="polite"></p>
    <section data-camera-view="grid">
        <div class="camera-monitor-shell"><header>Monitor en vivo <span>Selecciona un canal para ampliar</span></header><div class="camera-monitor-grid" data-camera-grid></div></div>
    </section>
    <section data-camera-view="single" hidden>
        <div class="camera-hmi-toolbar">
            <button type="button" data-camera-action="grid">← Volver a 2x2</button>
            <button type="button" data-camera-action="settings">Configurar cámara</button>
        </div>
        <div class="camera-single-layout"><div data-camera-single></div>
            <aside class="camera-hmi-panel"><h3 data-camera-single-name>Cámara</h3><p data-camera-single-coverage></p>
                <div class="camera-hmi-toolbar">
                    <button type="button" data-camera-action="play">Reproducir</button>
                    <button type="button" data-camera-action="mute">Activar audio</button>
                    <button type="button" data-camera-action="snapshot">Capturar imagen</button>
                    <button type="button" data-camera-action="zoom">Zoom: 1×</button>
                    <button type="button" data-camera-action="video-fullscreen">Ampliar imagen</button>
                </div>
                <p><strong>Calidad configurada:</strong> <span data-camera-quality-info>Sin cámara seleccionada</span></p>
                <p>La calidad depende de la señal web configurada. Pausar la imagen no detiene la grabación del DVR.</p>
                <dl data-camera-single-diagnostic></dl>
            </aside>
        </div>
    </section>
    <section data-camera-view="webcam" aria-labelledby="camera-webcam-title" hidden>
        <div class="camera-hmi-toolbar"><button type="button" data-camera-action="grid">← Volver a 2x2</button></div>
        <div class="camera-webcam-layout">
            <div class="camera-hmi-panel">
                <h3 id="camera-webcam-title">Cámara de este equipo</h3>
                <p>Prueba local con permiso del navegador y sin micrófono. La imagen no se envía al DVR.</p>
                <div class="camera-webcam-stage" data-camera-webcam-stage>
                    <video data-camera-webcam-video autoplay muted playsinline aria-label="Vista en vivo de la cámara de este equipo"></video>
                </div>
                <div class="camera-webcam-summary">
                    <span data-camera-webcam-status role="status" aria-live="polite">Desconectada</span>
                    <span>Resolución: <span data-camera-webcam-resolution>—</span></span>
                </div>
                <p class="camera-webcam-error" data-camera-webcam-error role="alert" hidden></p>
            </div>
            <aside class="camera-hmi-panel">
                <h3>Controles de prueba</h3>
                <div class="camera-hmi-toolbar camera-webcam-controls">
                    <button type="button" class="camera-hmi-primary" data-camera-action="webcam-connect">Conectar cámara</button>
                    <button type="button" data-camera-action="webcam-disconnect" disabled>Desconectar cámara</button>
                    <button type="button" data-camera-action="webcam-play" disabled>Pausar imagen</button>
                    <button type="button" data-camera-action="webcam-snapshot" disabled>Capturar imagen</button>
                    <button type="button" data-camera-action="webcam-fullscreen" disabled>Ampliar imagen</button>
                </div>
                <p>Al conectar, permite el acceso a la cámara. Si otra aplicación la utiliza, ciérrala y vuelve a intentar.</p>
                <p>Capturar imagen descarga una fotografía en este equipo. Esta etapa todavía no graba ni guarda vídeos.</p>
                <p>Al salir de esta vista o cerrar la página se desconecta la cámara. Para usarla desde otro equipo, la página necesita HTTPS.</p>
            </aside>
        </div>
    </section>
    <section data-camera-view="manage" hidden>
        <div class="camera-hmi-toolbar"><button type="button" data-camera-action="grid">← Volver a 2x2</button><button type="button" data-camera-action="create">Nueva cámara</button></div>
        <div class="camera-hmi-panel"><h3>Gestión de cámaras</h3><div data-camera-management></div></div>
    </section>
    <section data-camera-view="settings" hidden>
        <div class="camera-hmi-toolbar"><button type="button" data-camera-action="back">← Volver</button><h3 data-camera-settings-title>Configuración de cámara</h3></div>
        <form method="post" data-camera-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="camera_id" value="">
            <div class="camera-settings-layout">
                <nav class="camera-settings-nav" aria-label="Secciones de configuración">
                    <button type="button" data-camera-tab="device" aria-pressed="true"><i class="bi bi-camera-video"></i> Ajustes de dispositivo</button>
                    <button type="button" data-camera-tab="stream" aria-pressed="false"><i class="bi bi-diagram-3"></i> Red y streaming</button>
                    <button type="button" data-camera-tab="image" aria-pressed="false"><i class="bi bi-image"></i> Imagen y OSD</button>
                    <button type="button" data-camera-tab="diagnostic" aria-pressed="false"><i class="bi bi-activity"></i> Estado y diagnóstico</button>
                </nav>
                <div class="camera-hmi-panel">
                    <section data-camera-panel="device">
                        <h3>Ajustes de dispositivo</h3>
                        <div class="camera-settings-fields">
                            <label>Nombre<input name="name" maxlength="150" required></label>
                            <label>Estacionamiento<select name="parking_id" required><?php foreach ($parkings as $parking): ?><option value="<?= h($parking['id']) ?>"><?= h($parking['name']) ?></option><?php endforeach; ?></select></label>
                            <label>Canal físico<select name="channel_number" required><?php for ($channel = 1; $channel <= 4; $channel++): ?><option value="<?= $channel ?>">Canal <?= $channel ?></option><?php endfor; ?></select></label>
                            <label>Estado<select name="status"><option value="active">Activa</option><option value="inactive">Inactiva</option></select></label>
                            <label>Ubicación<input name="location" maxlength="150"></label>
                            <label>Cobertura<input name="coverage" maxlength="255"></label>
                        </div>
                    </section>
                    <section data-camera-panel="stream" hidden>
                        <h3>Red y streaming</h3>
                        <p>Configura una señal web real del canal. Las direcciones de red y los puertos dependen de la instalación del DVR.</p>
                        <div class="camera-settings-fields">
                            <label>Ruta interna del canal<input name="rtsp_path" maxlength="255" placeholder="/Streaming/Channels/102"><small>Solo ruta X01 / X02, sin credenciales.</small></label>
                            <label>Endpoint web<input name="stream_url" maxlength="500" type="url" placeholder="https://servidor/canal1/index.m3u8"><small>HTTP/HTTPS publicado por el transcodificador.</small></label>
                            <label>Protocolo<select name="stream_protocol"><option value="hls">HLS</option><option value="webrtc" disabled>WebRTC (pendiente de integración)</option></select></label>
                            <label>Calidad preferida<select name="preferred_quality"><option value="sub">Sub-stream (X02)</option><option value="main">Main-stream (X01)</option></select></label>
                        </div>
                        <p>El endpoint debe corresponder a la calidad seleccionada y no contener usuario ni contraseña. El navegador no reproduce una dirección RTSP directamente. WebRTC está pendiente de integración.</p>
                    </section>
                    <section data-camera-panel="image" hidden>
                        <h3>Imagen y OSD</h3><p>Ajustes locales del visor; no modifican el DVR.</p>
                        <?php foreach (['brightness' => 'Brillo', 'contrast' => 'Contraste', 'saturation' => 'Saturación'] as $field => $label): ?>
                        <label><?= h($label) ?> <output data-camera-output="<?= h($field) ?>">100%</output><input type="range" name="<?= h($field) ?>" min="0" max="200" value="100"></label>
                        <?php endforeach; ?>
                        <label class="camera-settings-check"><input type="checkbox" name="osd_datetime" value="1" checked> Mostrar fecha y hora del visor (America/Lima)</label>
                        <label class="camera-settings-check"><input type="checkbox" name="osd_camera_name" value="1" checked> OSD nombre de cámara</label>
                    </section>
                    <section data-camera-panel="diagnostic" hidden>
                        <h3>Estado y diagnóstico</h3><dl data-camera-settings-diagnostic></dl>
                        <div class="camera-hmi-toolbar"><button type="button" data-camera-action="test">Probar conexión guardada</button></div>
                        <p>La prueba usa el endpoint guardado en el navegador. Guarda primero los cambios de red.</p>
                    </section>
                    <div class="camera-settings-save"><button type="submit" class="camera-hmi-primary">Guardar cámara</button></div>
                </div>
            </div>
        </form>
        <form method="post" class="camera-settings-danger" data-camera-delete hidden>
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="camera_id"><input type="hidden" name="confirm_delete" value="1">
            <div><strong>Eliminar cámara</strong><p>El canal quedará disponible. Esta acción requiere confirmación.</p></div><button type="submit">Eliminar cámara</button>
        </form>
    </section>
    <noscript>Activa JavaScript para utilizar el centro de monitoreo.</noscript>
</div>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.6.13/dist/hls.min.js" crossorigin="anonymous"></script>
<script src="<?= h(app_url('/frontend/assets/js/cameras.js')) ?>" defer></script>
<?php render_page_end(); ?>
