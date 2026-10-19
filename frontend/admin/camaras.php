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
<div class="camera-hmi" data-camera-module data-recordings-endpoint="<?= h(app_url('/frontend/admin/grabaciones.php')) ?>" data-recordings-csrf="<?= h(csrf_token()) ?>">
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
        <button type="button" data-camera-action="recordings"><i class="bi bi-calendar3" aria-hidden="true"></i> Ver grabaciones</button>
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
                <h3>Controles de cámara</h3>
                <div class="camera-hmi-toolbar camera-webcam-controls">
                    <button type="button" class="camera-hmi-primary" data-camera-action="webcam-connect">Conectar cámara</button>
                    <button type="button" data-camera-action="webcam-disconnect" disabled>Desconectar cámara</button>
                    <button type="button" data-camera-action="webcam-play" disabled>Pausar imagen</button>
                    <button type="button" data-camera-action="webcam-snapshot" disabled>Capturar imagen</button>
                    <button type="button" data-camera-action="webcam-fullscreen" disabled>Ampliar imagen</button>
                </div>
                <p>Al conectar, permite el acceso a la cámara. Si otra aplicación la utiliza, ciérrala y vuelve a intentar.</p>
                <p>Capturar imagen descarga una fotografía. Durante la grabación se bloquean la pausa y la desconexión: utiliza Detener y guardar.</p>
                <div class="camera-recording-controls" aria-labelledby="camera-recording-title">
                    <h3 id="camera-recording-title">Grabar vídeo</h3>
                    <div class="camera-recording-summary">
                        <span data-recording-status role="status" aria-live="polite">Sin grabación en curso</span>
                        <span class="camera-recording-timer" data-recording-timer aria-label="Duración de la grabación">00:00</span>
                    </div>
                    <p data-recording-limits>Hasta 2 minutos y 20 MB por vídeo.</p>
                    <div class="camera-hmi-toolbar camera-webcam-controls">
                        <button type="button" class="camera-hmi-primary" data-recording-action="start" disabled><i class="bi bi-record-circle" aria-hidden="true"></i> Iniciar grabación</button>
                        <button type="button" data-recording-action="stop" disabled><i class="bi bi-stop-circle" aria-hidden="true"></i> Detener y guardar</button>
                        <button type="button" data-recording-action="retry" hidden>Reintentar guardado</button>
                        <button type="button" data-recording-action="download-local" hidden>Descargar copia</button>
                    </div>
                    <p class="camera-webcam-error" data-recording-error role="alert" hidden></p>
                    <p class="camera-recording-progress" data-recording-progress role="status" aria-live="polite" hidden></p>
                    <video class="camera-recording-preview" data-recording-local-preview controls playsinline preload="metadata" aria-label="Vista previa de la grabación sin guardar" hidden></video>
                    <details class="camera-recording-discard" data-recording-discard hidden>
                        <summary>Descartar grabación sin guardar</summary>
                        <p>Se eliminará la grabación pendiente. Descarga una copia si deseas conservarla.</p>
                        <button type="button" data-recording-action="discard">Sí, descartar grabación</button>
                    </details>
                </div>
                <p>Mantén esta pestaña visible al grabar. Al ocultarla se detendrá el vídeo y se intentará guardarlo. Espera la confirmación antes de cerrar.</p>
                <p>Esta prueba depende de la cámara y del navegador de este equipo. Para acceder desde otro equipo, la página necesita HTTPS.</p>
            </aside>
        </div>
        <section class="camera-hmi-panel camera-recordings-panel" aria-labelledby="camera-recordings-title">
            <div class="camera-recordings-heading">
                <div><h3 id="camera-recordings-title">Grabaciones guardadas</h3><p>Elige un día y una hora para encontrar y reproducir tus vídeos.</p></div>
                <button type="button" data-recording-action="refresh"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Actualizar grabaciones</button>
            </div>
            <p class="camera-library__note">Horas de Lima. Se muestran únicamente los fragmentos guardados; los intervalos sin vídeos no tienen grabación disponible.</p>
            <p data-recordings-feedback role="status" aria-live="polite"></p>
            <div class="camera-library__layout">
                <section class="camera-library__calendar" aria-labelledby="camera-recordings-month-title">
                    <div class="camera-library__month-controls">
                        <button type="button" data-recording-action="previous-month" aria-label="Mes anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <label for="camera-recordings-month">Mes de grabaciones<input type="month" id="camera-recordings-month" data-recordings-month></label>
                        <button type="button" data-recording-action="next-month" aria-label="Mes siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </div>
                    <h4 id="camera-recordings-month-title" data-recordings-month-title>Calendario de grabaciones</h4>
                    <div class="camera-library__weekdays" aria-hidden="true"><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span></div>
                    <div class="camera-library__days" data-recordings-days role="group" aria-label="Días del mes"></div>
                    <p class="camera-library__calendar-status" data-recordings-calendar-status role="status" aria-live="polite"></p>
                    <p class="camera-webcam-error" data-recordings-calendar-error role="alert" hidden></p>
                </section>
                <section class="camera-library__detail" aria-labelledby="camera-recordings-selected-date">
                    <h4 id="camera-recordings-selected-date" data-recordings-selected-date>Selecciona un día</h4>
                    <p class="camera-library__hours-label" id="camera-recordings-hours-label">Filtrar por hora</p>
                    <div class="camera-library__hours" data-recordings-hours role="group" aria-labelledby="camera-recordings-hours-label"></div>
                    <p class="camera-library__summary" data-recordings-summary role="status" aria-live="polite"></p>
                    <p class="camera-recordings-empty" data-recordings-empty>Aún no hay grabaciones guardadas.</p>
                    <div class="camera-recordings-list" data-recordings-list></div>
                    <nav class="camera-library__pagination" data-recordings-pagination aria-label="Páginas de grabaciones" hidden>
                        <button type="button" data-recording-action="previous-page">Anterior</button>
                        <span data-recordings-page aria-live="polite"></span>
                        <button type="button" data-recording-action="next-page">Siguiente</button>
                    </nav>
                </section>
            </div>
            <div class="camera-recordings-player" data-recordings-player hidden>
                <h4 data-recordings-player-title>Grabación seleccionada</h4>
                <video data-recordings-video controls playsinline preload="metadata" aria-label="Reproducción de la grabación guardada"></video>
                <section class="camera-clip-editor" data-clip-editor aria-labelledby="camera-clip-editor-title" hidden>
                    <h5 id="camera-clip-editor-title">Recortar y exportar</h5>
                    <p class="camera-clip-source" data-clip-source-info role="status" aria-live="polite"></p>
                    <form data-clip-form novalidate>
                        <p id="camera-clip-format-help">Indica el inicio y el fin en minutos:segundos, con décimas. Por ejemplo, 00:12.5 corresponde a 12 segundos y medio.</p>
                        <div class="camera-clip-fields">
                            <div class="camera-clip-field">
                                <label for="camera-clip-start">Inicio (minutos:segundos)</label>
                                <input type="text" id="camera-clip-start" data-clip-start inputmode="decimal" value="00:00.0" placeholder="00:00.0" maxlength="14" autocomplete="off" spellcheck="false" required aria-describedby="camera-clip-format-help">
                                <button type="button" data-clip-action="mark-start" disabled>Usar posición actual como inicio</button>
                            </div>
                            <div class="camera-clip-field">
                                <label for="camera-clip-end">Fin (minutos:segundos)</label>
                                <input type="text" id="camera-clip-end" data-clip-end inputmode="decimal" value="00:00.0" placeholder="00:00.0" maxlength="14" autocomplete="off" spellcheck="false" required aria-describedby="camera-clip-format-help">
                                <button type="button" data-clip-action="mark-end" disabled>Usar posición actual como fin</button>
                            </div>
                        </div>
                        <p id="camera-clip-original-help" class="camera-clip-original">El vídeo original se conserva. Se generará un archivo MP4 con el intervalo seleccionado.</p>
                        <div class="camera-clip-actions">
                            <button type="button" data-clip-action="preview-selection" disabled>Ver selección</button>
                            <button type="submit" class="camera-hmi-primary" data-clip-action="export" aria-describedby="camera-clip-original-help" disabled><i class="bi bi-scissors" aria-hidden="true"></i> Generar MP4</button>
                        </div>
                    </form>
                    <p class="camera-webcam-error" data-clip-error role="alert" hidden></p>
                    <p class="camera-clip-status" data-clip-status role="status" aria-live="polite">Selecciona un vídeo guardado para recortarlo.</p>
                    <section class="camera-clip-result" data-clip-result aria-labelledby="camera-clip-result-title" hidden>
                        <h6 id="camera-clip-result-title">Fragmento preparado</h6>
                        <video data-clip-video controls playsinline preload="metadata" aria-label="Vista previa del fragmento"></video>
                        <div class="camera-clip-actions">
                            <a class="camera-clip-download" data-clip-download download><i class="bi bi-download" aria-hidden="true"></i> Descargar MP4</a>
                            <button type="button" data-clip-action="prepare-share">Preparar para compartir</button>
                            <button type="button" data-clip-action="share" hidden><i class="bi bi-share" aria-hidden="true"></i> Compartir archivo</button>
                        </div>
                        <p data-clip-share-status role="status" aria-live="polite"></p>
                        <p class="camera-clip-share-help">Si tu navegador no permite compartir archivos, descarga el MP4 y adjúntalo en WhatsApp u otra aplicación.</p>
                    </section>
                </section>
            </div>
        </section>
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
<script src="<?= h(app_url('/frontend/assets/js/webcam-recording.js')) ?>?v=<?= h((string) filemtime(__DIR__ . '/../assets/js/webcam-recording.js')) ?>" defer></script>
<script src="<?= h(app_url('/frontend/assets/js/cameras.js')) ?>?v=<?= h((string) filemtime(__DIR__ . '/../assets/js/cameras.js')) ?>" defer></script>
<?php render_page_end(); ?>
