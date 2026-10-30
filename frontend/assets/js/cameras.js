/* HMI de cámaras: navegación y reproductores encapsulados. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-camera-module]').forEach((root) => {
        const find = (selector) => root.querySelector(selector);
        const cameras = JSON.parse(find('[data-camera-data]').textContent);
        const parking = find('[data-camera-parking]');
        const form = find('[data-camera-form]');
        const deletion = find('[data-camera-delete]');
        const players = new Map();
        const liveQualities = new Map();
        let selected = null;
        let currentView = 'grid';
        let returnView = 'grid';
        let globalMuted = true;
        let zoom = 1;
        const webcamVideo = find('[data-camera-webcam-video]');
        let webcamStream = null;
        let webcamRequest = 0;
        let webcamPending = false;
        let recording = null;
        const bridgeRequests = new Set();
        const checkBridge = async () => {
            const output = find('[data-bridge-status]');
            const controller = new AbortController();
            bridgeRequests.add(controller);
            const timeout = setTimeout(() => controller.abort(), 4000);
            try {
                const response = await fetch(root.dataset.streamEndpoint + '?action=status', {
                    credentials: 'same-origin', cache: 'no-store', signal: controller.signal
                });
                const status = await response.json();
                output.textContent = status.message || status.error || 'No se pudo comprobar el servicio de cámaras.';
                output.dataset.state = response.ok && status.running ? 'online' : 'error';
                if (response.ok) {
                    root.dataset.bridgeConfigured = status.configured ? '1' : '0';
                    find('[data-bridge-enable-button]').disabled = !status.configured;
                }
            } catch { output.textContent = 'No se pudo comprobar el servicio. Revisa la conexión del servidor.'; }
            finally { clearTimeout(timeout); bridgeRequests.delete(controller); }
        };
        const message = (text) => { find('[data-camera-message]').textContent = text; };
        const element = (tag, className, text) => {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        };
        const button = (text, callback) => {
            const node = element('button', '', text);
            node.type = 'button';
            node.addEventListener('click', callback);
            return node;
        };
        const list = () => cameras.filter((camera) => Number(camera.parking_id) === Number(parking.value));
        const cameraAt = (channel) => list().find((camera) => Number(camera.channel_number) === channel);
        const firstCamera = () => list().find((camera) => camera.channel_number);
        const frameAvailable = (video) => Boolean(video?.videoWidth && video.readyState >= 2);
        const webcamConnected = () => Boolean(webcamStream?.getVideoTracks().some((track) => track.readyState === 'live'));
        const updateWebcamControls = () => {
            const connected = webcamConnected();
            const ready = connected && frameAvailable(webcamVideo);
            find('[data-camera-action="webcam-connect"]').disabled = webcamPending || connected;
            find('[data-camera-action="webcam-disconnect"]').disabled = recording?.isCapturing() || (!webcamPending && !webcamStream);
            find('[data-camera-action="webcam-play"]').disabled = !ready || recording?.isCapturing();
            find('[data-camera-action="webcam-play"]').textContent = webcamVideo.paused ? 'Reproducir' : 'Pausar imagen';
            find('[data-camera-action="webcam-snapshot"]').disabled = !ready;
            find('[data-camera-action="webcam-fullscreen"]').disabled = !ready || !find('[data-camera-webcam-stage]').requestFullscreen;
            find('[data-camera-webcam-resolution]').textContent = ready
                ? webcamVideo.videoWidth + ' × ' + webcamVideo.videoHeight : 'No disponible';
            recording?.refreshAvailability();
        };
        const webcamStatus = (status, error = '') => {
            find('[data-camera-webcam-status]').textContent = status;
            const alert = find('[data-camera-webcam-error]');
            alert.textContent = error;
            alert.hidden = !error;
            updateWebcamControls();
        };
        const stopWebcam = (status = 'Desconectada', error = '') => {
            recording?.handleSourceEnd();
            webcamRequest++;
            webcamPending = false;
            const stream = webcamStream;
            webcamStream = null;
            webcamVideo.pause();
            webcamVideo.srcObject = null;
            stream?.getTracks().forEach((track) => track.stop());
            webcamStatus(status, error);
        };
        const connectWebcam = async () => {
            if (webcamPending || webcamConnected()) return;
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                webcamStatus('No disponible', 'Abre el sistema desde localhost o mediante HTTPS en un navegador compatible para usar la cámara.');
                return;
            }
            const request = ++webcamRequest;
            webcamPending = true;
            webcamStatus('Solicitando permiso');
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false
                });
                // A permission prompt may finish after disconnecting or leaving this view.
                if (request !== webcamRequest || currentView !== 'webcam' || !root.isConnected) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }
                webcamPending = false;
                webcamStream = stream;
                if (!webcamConnected()) {
                    stopWebcam('Sin señal', 'La cámara no entregó una pista de vídeo disponible. Intenta conectarla otra vez.');
                    return;
                }
                stream.getVideoTracks().forEach((track) => {
                    track.addEventListener('ended', () => {
                        if (webcamStream === stream) stopWebcam('Desconectada', 'La cámara se desconectó o dejó de estar disponible. Vuelve a conectarla.');
                    }, { once: true });
                });
                webcamVideo.srcObject = stream;
                webcamStatus('Conectando');
                await webcamVideo.play().catch(() => {
                    if (webcamStream === stream) webcamStatus('Imagen pausada', 'Pulsa Reproducir para mostrar la imagen de la cámara.');
                });
            } catch (error) {
                if (request !== webcamRequest) return;
                const errors = {
                    NotAllowedError: 'Permiso de cámara denegado. Permite el acceso en el navegador y vuelve a conectar.',
                    SecurityError: 'El navegador bloqueó el acceso a la cámara. Revisa los permisos de este sitio.',
                    NotFoundError: 'No se encontró una cámara. Conecta o habilita la cámara del equipo y vuelve a intentar.',
                    NotReadableError: 'No se pudo abrir la cámara. Cierra otras aplicaciones que la estén usando y vuelve a intentar.',
                    OverconstrainedError: 'La cámara no admite la configuración solicitada. Prueba con otra cámara del equipo.',
                    AbortError: 'La conexión con la cámara se interrumpió. Vuelve a conectar.'
                };
                stopWebcam('Sin conexión', errors[error.name] || 'No se pudo conectar la cámara. Comprueba el dispositivo y los permisos del navegador.');
            }
        };
        const safeEndpoint = (value) => {
            if (!value) return false;
            try {
                const url = new URL(value);
                return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password;
            } catch { return false; }
        };
        const destroyPlayers = () => {
            players.forEach((player) => {
                player.disposed = true;
                clearTimeout(player.timer);
                player.hls?.destroy();
                player.rtc?.dispose();
                player.video.pause();
                player.video.removeAttribute('src');
                player.video.load();
            });
            players.clear();
            refreshControls();
        };
        const refreshControls = () => {
            const player = selected && players.get(Number(selected.id));
            find('[data-camera-action="single"]').disabled = !firstCamera();
            find('[data-camera-action="create"]').disabled = [1, 2, 3, 4].every((channel) => cameraAt(channel));
            find('[data-camera-action="reconnect"]').disabled = ![...players.values()].some(item => item.rtc && !item.disposed);
            const ready = Boolean(player?.canPlay && frameAvailable(player.video) && !player.failed);
            const mutedPlayers = [...players.values()].filter((item) => item.hasAudio && !item.disposed);
            const audio = find('[data-camera-action="audio"]');
            audio.disabled = !mutedPlayers.length || currentView === 'webcam';
            const allMuted = mutedPlayers.every((item) => item.video.muted);
            audio.textContent = allMuted ? 'Audio global: silenciado' : 'Audio global: activado';
            audio.setAttribute('aria-pressed', String(!allMuted));
            root.querySelectorAll('[data-camera-action="play"],[data-camera-player-play]').forEach((node) => {
                node.disabled = !player?.canPlay || player.failed || !player.loaded;
                node.textContent = player?.video.paused === false ? 'Pausar imagen' : 'Reproducir';
            });
            for (const action of ['snapshot', 'zoom', 'video-fullscreen', 'mute']) {
                const node = find('[data-camera-action="' + action + '"]');
                node.disabled = !ready || (action === 'mute' && !player?.hasAudio)
                    || (action === 'video-fullscreen' && !find('[data-camera-single]').requestFullscreen);
            }
            const mute = find('[data-camera-action="mute"]');
            mute.textContent = player?.video.muted === false ? 'Silenciar audio' : 'Activar audio';
            mute.setAttribute('aria-pressed', String(player?.video.muted === false));
            const test = find('[data-camera-action="test"]');
            test.disabled = !selected || selected.status !== 'active'
                || (selected.stream_protocol !== 'webrtc' && !safeEndpoint(selected.stream_url));
        };
        const diagnostic = (container, camera) => {
            container.replaceChildren();
            const player = camera && players.get(Number(camera.id));
            const resolution = player?.video.videoWidth ? player.video.videoWidth + ' × ' + player.video.videoHeight : 'No disponible';
            const rows = {
                'Estado de cámara': camera ? (camera.status === 'active' ? 'Activa' : 'Inactiva') : 'No configurada',
                'Protocolo': camera?.stream_protocol?.toUpperCase() || 'No disponible',
                'Calidad solicitada': camera ? ((player?.rtc?.quality || camera.preferred_quality) === 'main' ? 'Principal' : 'Ligera') : 'No disponible',
                'Resolución recibida': resolution,
                'Bitrate recibido': player?.rtc?.metrics.kbps === undefined ? 'Sin telemetría' : player.rtc.metrics.kbps + ' kb/s',
                'RTT de red': player?.rtc?.metrics.rtt == null ? 'Sin telemetría' : player.rtc.metrics.rtt + ' ms',
                'Fotogramas / segundo': player?.rtc?.metrics.fps ?? 'Sin telemetría',
                'Reproductor': player?.state || (camera?.stream_protocol === 'webrtc' || camera?.stream_url ? 'Sin iniciar' : 'Fuente no configurada')
            };
            Object.entries(rows).forEach(([label, value]) => container.append(element('dt', '', label), element('dd', '', value)));
        };
        const refreshDiagnostics = () => {
            diagnostic(find('[data-camera-single-diagnostic]'), selected);
            diagnostic(find('[data-camera-settings-diagnostic]'), selected);
            refreshControls();
        };
        const startPlayer = (camera, video, overlay, badge, single) => {
            const player = { video, hls: null, timer: null, state: 'Sin iniciar', disposed: false,
                canPlay: false, loaded: false, failed: false, hasAudio: false };
            players.set(Number(camera.id), player);
            const setState = (state, kind = '') => {
                if (player.disposed) return;
                player.state = state;
                badge.textContent = state;
                badge.dataset.state = kind;
                overlay.lastChild.textContent = state;
                overlay.hidden = kind === 'online';
                refreshDiagnostics();
            };
            const noSignal = () => {
                if (player.rtc) { player.rtc.fail('No se recibió vídeo compatible del canal.'); return; }
                clearTimeout(player.timer); player.failed = true; setState('Canal sin señal', 'error');
            };
            video.addEventListener('playing', () => {
                if (!player.canPlay || player.disposed) return;
                clearTimeout(player.timer); player.failed = false; player.loaded = true; setState('En línea', 'online');
            });
            video.addEventListener('pause', () => { if (player.canPlay && frameAvailable(video) && !player.failed) setState('Imagen pausada'); });
            video.addEventListener('error', noSignal);
            video.addEventListener('loadedmetadata', () => {
                if (player.disposed) return;
                player.loaded = true;
                player.hasAudio = player.hasAudio || Boolean(video.audioTracks?.length || video.mozHasAudio || video.webkitAudioDecodedByteCount > 0);
                refreshDiagnostics();
            });
            video.addEventListener('loadeddata', refreshDiagnostics);
            video.addEventListener('volumechange', refreshControls);
            video.addEventListener('waiting', () => {
                if (player.rtc) return;
                if (!player.canPlay || player.disposed) return;
                setState('Esperando señal');
                clearTimeout(player.timer);
                player.timer = setTimeout(noSignal, 15000);
            });
            const play = () => {
                if (!player.canPlay || player.disposed || player.failed) return;
                video.play().catch(() => {
                    if (!player.failed && !player.disposed) setState('Pulsa Reproducir para mostrar la imagen');
                });
            };
            player.play = play;
            if (camera.status !== 'active') { setState('Inactiva'); return; }
            if (camera.stream_protocol === 'webrtc') {
                if (!window.DeviozCameraWebRTC) { setState('No se pudo cargar el reproductor WebRTC. Recarga la página.', 'error'); return; }
                player.canPlay = true;
                player.rtc = new window.DeviozCameraWebRTC({
                    video, endpoint: root.dataset.streamEndpoint, csrf: root.dataset.recordingsCsrf,
                    cameraId: Number(camera.id), quality: single ? (liveQualities.get(Number(camera.id)) || camera.preferred_quality || 'sub') : 'sub',
                    onState: (state, kind = '') => {
                        if (player.disposed) return;
                        player.failed = kind === 'error';
                        setState(state, kind);
                    },
                    onStats: () => { if (!player.disposed) refreshDiagnostics(); }
                });
                refreshControls();
                return;
            }
            if (!safeEndpoint(camera.stream_url)) { setState('Stream no configurado'); return; }
            if (location.protocol === 'https:' && new URL(camera.stream_url).protocol === 'http:') {
                setState('Endpoint HTTP bloqueado en página HTTPS', 'error'); return;
            }
            player.canPlay = true;
            setState('Conectando');
            player.timer = setTimeout(noSignal, 15000);
            try {
                if (window.Hls?.isSupported()) {
                    player.hls = new window.Hls({ enableWorker: true });
                    player.hls.on(window.Hls.Events.MANIFEST_PARSED, play);
                    player.hls.on(window.Hls.Events.BUFFER_CODECS, (_event, tracks) => {
                        if (player.disposed) return;
                        player.hasAudio = Boolean(tracks.audio);
                        refreshControls();
                    });
                    player.hls.on(window.Hls.Events.ERROR, (_event, data) => {
                        if (data.fatal && !player.disposed) {
                            const hls = player.hls;
                            player.hls = null;
                            noSignal();
                            hls?.destroy();
                        }
                    });
                    player.hls.loadSource(camera.stream_url);
                    player.hls.attachMedia(video);
                } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                    video.src = camera.stream_url;
                    play();
                } else {
                    clearTimeout(player.timer);
                    player.canPlay = false;
                    setState('HLS no disponible en este navegador', 'error');
                }
            } catch { noSignal(); }
        };
        const card = (channel, camera, single = false) => {
            const article = element('article', 'camera-channel');
            const stage = element('div', 'camera-channel-stage');
            const overlay = element('div', 'camera-channel-overlay');
            overlay.append(element('i', 'bi bi-camera-video-off'), element('span', '', 'No configurado'));
            const top = element('div', 'camera-channel-top');
            const badge = element('span', 'camera-channel-badge', 'No configurado');
            top.append(element('span', 'camera-channel-tag', 'CANAL ' + channel), badge);
            const bottom = element('div', 'camera-channel-bottom');
            const title = element('div');
            title.append(element('strong', '', camera?.name || 'Canal ' + channel), element('small', '', camera?.location || camera?.coverage || (camera ? 'Ubicación sin especificar' : 'No configurado')));
            const controls = element('div', 'camera-channel-controls');
            if (camera) {
                const video = element('video');
                video.autoplay = true;
                video.muted = globalMuted;
                video.playsInline = true;
                video.crossOrigin = 'anonymous';
                video.style.filter = 'brightness(' + camera.brightness + '%) contrast(' + camera.contrast + '%) saturate(' + camera.saturation + '%)';
                stage.append(video);
                const osd = element('div', 'camera-channel-osd');
                osd.dataset.cameraOsd = String(camera.id);
                stage.append(osd);
                const playButton = button(single ? 'Reproducir' : 'Ampliar', () => single ? togglePlay() : openSingle(camera));
                if (single) { playButton.dataset.cameraPlayerPlay = ''; playButton.disabled = true; }
                controls.append(playButton);
                controls.append(button('Configurar', () => openSettings(camera)));
                if (!single) {
                    stage.tabIndex = 0;
                    stage.setAttribute('role', 'button');
                    stage.setAttribute('aria-label', 'Ampliar ' + camera.name);
                    stage.addEventListener('click', () => openSingle(camera));
                    stage.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openSingle(camera); }
                    });
                }
                // Start after DOM insertion; events and data stay local to this root.
                queueMicrotask(() => { if (article.isConnected) startPlayer(camera, video, overlay, badge, single); });
            } else {
                controls.append(button('Configurar canal', () => openSettings(null, channel)));
            }
            stage.append(overlay, top);
            bottom.append(title, controls);
            article.append(stage, bottom);
            return article;
        };
        const show = (view) => {
            if (currentView === 'webcam' && view !== 'webcam') stopWebcam();
            currentView = view;
            parking.disabled = view === 'webcam';
            root.querySelectorAll('[data-camera-view]').forEach((node) => { node.hidden = node.dataset.cameraView !== view; });
            root.querySelectorAll('[data-camera-action="grid"],[data-camera-action="single"],[data-camera-action="manage"],[data-camera-action="webcam"]').forEach((node) => {
                node.setAttribute('aria-pressed', String(node.dataset.cameraAction === view));
            });
            refreshControls();
        };
        const renderGrid = () => {
            destroyPlayers();
            show('grid');
            const grid = find('[data-camera-grid]');
            grid.replaceChildren();
            for (let channel = 1; channel <= 4; channel++) grid.append(card(channel, cameraAt(channel)));
            message('');
        };
        const openSingle = (camera) => {
            if (!camera) { message('Selecciona un canal configurado.'); return; }
            destroyPlayers();
            selected = camera;
            zoom = 1;
            find('[data-camera-action="zoom"]').textContent = 'Zoom: 1×';
            find('[data-camera-single]').replaceChildren(card(Number(camera.channel_number), camera, true));
            find('[data-camera-single-name]').textContent = camera.name;
            find('[data-camera-single-coverage]').textContent = [camera.location, camera.coverage].filter(Boolean).join(' · ');
            find('[data-camera-quality-info]').textContent = (liveQualities.get(Number(camera.id)) || camera.preferred_quality) === 'main' ? 'Principal' : 'Ligera';
            find('[data-camera-live-quality]').value = liveQualities.get(Number(camera.id)) || camera.preferred_quality || 'sub';
            find('[data-camera-live-quality]').disabled = camera.stream_protocol !== 'webrtc';
            show('single');
            refreshDiagnostics();
            message('');
        };
        const tab = (name) => {
            root.querySelectorAll('[data-camera-panel]').forEach((node) => { node.hidden = node.dataset.cameraPanel !== name; });
            root.querySelectorAll('[data-camera-tab]').forEach((node) => { node.setAttribute('aria-pressed', String(node.dataset.cameraTab === name)); });
        };
        const defaults = (channel) => ({
            parking_id: parking.value, name: '', channel_number: channel, location: '', coverage: '', status: 'active',
            rtsp_path: '/Streaming/Channels/' + channel + '02', stream_url: '', stream_protocol: 'hls',
            preferred_quality: 'sub', brightness: 100, contrast: 100, saturation: 100, osd_datetime: 1, osd_camera_name: 1
        });
        const openSettings = (camera, channel = 1) => {
            const previousPreview = find('[data-camera-test-preview]');
            destroyPlayers();
            if (previousPreview) previousPreview.remove();
            if (currentView !== 'settings') returnView = currentView === 'single' ? 'single' : (currentView === 'manage' ? 'manage' : 'grid');
            selected = camera;
            const data = camera || defaults(channel);
            form.reset();
            Object.entries(data).forEach(([key, value]) => {
                const input = form.elements.namedItem(key);
                if (!input) return;
                if (input.type === 'checkbox') input.checked = Number(value) === 1;
                else input.value = value ?? '';
            });
            form.elements.action.value = camera ? 'update' : 'create';
            form.elements.camera_id.value = camera?.id || '';
            deletion.hidden = !camera;
            deletion.elements.camera_id.value = camera?.id || '';
            updateStreamFields();
            find('[data-camera-settings-title]').textContent = camera ? 'Configuración · ' + camera.name : 'Configurar canal ' + channel;
            ['brightness', 'contrast', 'saturation'].forEach((key) => { find('[data-camera-output="' + key + '"]').textContent = data[key] + '%'; });
            tab('device');
            show('settings');
            refreshDiagnostics();
            message(data.stream_protocol === 'webrtc'
                ? 'WebRTC utiliza el DVR configurado por el operador. Los ajustes de imagen son locales al visor.' : '');
        };
        const manage = () => {
            destroyPlayers();
            const container = find('[data-camera-management]');
            container.replaceChildren();
            list().forEach((camera) => {
                const row = element('div', 'camera-management-row');
                row.append(element('span', '', camera.name + ' · ' + (camera.channel_number ? 'Canal ' + camera.channel_number : 'Sin canal asignado')), button('Configurar', () => openSettings(camera)));
                container.append(row);
            });
            if (!list().length) container.append(element('p', '', 'No hay cámaras registradas.'));
            show('manage');
        };
        const togglePlay = () => {
            const player = selected && players.get(Number(selected.id));
            if (!player?.canPlay || player.failed || !player.loaded) return;
            if (player.video.paused) player.play?.();
            else player.video.pause();
        };
        const fullscreen = async (node) => {
            try {
                if (document.fullscreenElement) await document.exitFullscreen();
                else if (node?.requestFullscreen) await node.requestFullscreen();
                else message('Pantalla completa no disponible.');
            } catch { message('No se pudo abrir pantalla completa.'); }
        };
        const snapshot = (webcam = false) => {
            const video = webcam ? webcamVideo : (selected && players.get(Number(selected.id))?.video);
            const filename = webcam ? 'camara-equipo' : 'canal-' + selected?.channel_number;
            if (!video?.videoWidth || video.readyState < 2) { message('No hay un fotograma disponible para capturar.'); return; }
            try {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const context = canvas.getContext('2d');
                context.filter = video.style.filter;
                context.drawImage(video, 0, 0);
                canvas.toBlob((blob) => {
                    if (!blob) { message('No se pudo generar la captura.'); return; }
                    const url = URL.createObjectURL(blob);
                    const link = element('a');
                    link.href = url;
                    link.download = filename + '-' + Date.now() + '.png';
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                    message('Captura generada; descarga solicitada al navegador.');
                }, 'image/png');
            } catch { message('No se pudo capturar el vídeo. Verifica los permisos CORS del endpoint.'); }
        };
        root.addEventListener('click', (event) => {
            const target = event.target.closest('[data-camera-action],[data-camera-tab]');
            if (!target || !root.contains(target)) return;
            if (target.disabled) return;
            if (target.dataset.cameraTab) { tab(target.dataset.cameraTab); return; }
            const action = target.dataset.cameraAction;
            if (action === 'reconnect') { players.forEach(player => player.rtc?.retry()); return; }
            if (action === 'bridge-status') { checkBridge(); return; }
            if (currentView === 'webcam' && ['grid', 'single', 'manage', 'create', 'settings', 'back'].includes(action)
                && recording?.preventLeaving()) return;
            if (action === 'grid') renderGrid();
            else if (action === 'webcam' || action === 'recordings') {
                destroyPlayers();
                show('webcam');
                updateWebcamControls();
                message('');
                if (action === 'recordings') {
                    const title = find('#camera-recordings-title');
                    title.tabIndex = -1;
                    title.focus({ preventScroll: true });
                    find('.camera-recordings-panel').scrollIntoView({ block: 'start', behavior: 'instant' });
                }
            }
            else if (action === 'webcam-connect') connectWebcam();
            else if (action === 'webcam-disconnect') stopWebcam();
            else if (action === 'webcam-play') {
                if (!webcamConnected() || !frameAvailable(webcamVideo)) return;
                if (webcamVideo.paused) webcamVideo.play().catch(() => {
                    if (webcamConnected()) webcamStatus('Imagen pausada', 'No se pudo reproducir la imagen. Vuelve a conectar la cámara.');
                });
                else webcamVideo.pause();
            }
            else if (action === 'webcam-snapshot') snapshot(true);
            else if (action === 'webcam-fullscreen') fullscreen(find('[data-camera-webcam-stage]'));
            else if (action === 'single') openSingle(selected && Number(selected.parking_id) === Number(parking.value) ? selected : firstCamera());
            else if (action === 'manage') manage();
            else if (action === 'create') {
                const channel = [1, 2, 3, 4].find((number) => !cameraAt(number));
                if (channel) openSettings(null, channel);
                else message('Los cuatro canales están configurados en este estacionamiento.');
            }
            else if (action === 'settings') openSettings(selected);
            else if (action === 'back') {
                if (returnView === 'single' && selected) openSingle(selected);
                else if (returnView === 'manage') manage();
                else renderGrid();
            }
            else if (action === 'play') togglePlay();
            else if (action === 'mute') {
                const player = selected && players.get(Number(selected.id));
                if (player?.hasAudio && frameAvailable(player.video) && !player.failed) {
                    player.video.muted = !player.video.muted;
                    globalMuted = [...players.values()].every((item) => item.video.muted);
                    refreshControls();
                }
            }
            else if (action === 'audio') {
                globalMuted = !globalMuted;
                players.forEach((player) => { if (player.hasAudio) player.video.muted = globalMuted; });
                refreshControls();
            }
            else if (action === 'fullscreen') fullscreen(root);
            else if (action === 'video-fullscreen') fullscreen(find('[data-camera-single] .camera-channel'));
            else if (action === 'snapshot') snapshot();
            else if (action === 'zoom') {
                const video = selected && players.get(Number(selected.id))?.video;
                zoom = zoom >= 3 ? 1 : zoom + 0.5;
                if (video) video.style.transform = 'scale(' + zoom + ')';
                target.textContent = 'Zoom: ' + zoom + '×';
            }
            else if (action === 'test') {
                if (!selected || selected.status !== 'active'
                    || (selected.stream_protocol !== 'webrtc' && !safeEndpoint(selected.stream_url))) return;
                destroyPlayers();
                const preview = card(Number(selected.channel_number), selected, true);
                // A visible preview in diagnostics also works when opened from Gestión.
                let holder = find('[data-camera-test-preview]');
                if (!holder) {
                    holder = element('div');
                    holder.dataset.cameraTestPreview = '';
                    find('[data-camera-panel="diagnostic"]').append(holder);
                }
                holder.replaceChildren(preview);
                message('Prueba del endpoint guardado iniciada en el navegador.');
            }
        });
        let previousParking = parking.value;
        parking.addEventListener('change', () => {
            if (recording?.preventLeaving()) { parking.value = previousParking; return; }
            previousParking = parking.value;
            selected = null; renderGrid();
        });
        form.addEventListener('input', (event) => {
            if (['brightness', 'contrast', 'saturation'].includes(event.target.name)) {
                find('[data-camera-output="' + event.target.name + '"]').textContent = event.target.value + '%';
            }
        });
        const updatePath = () => {
            form.elements.rtsp_path.value = '/Streaming/Channels/' + form.elements.channel_number.value + (form.elements.preferred_quality.value === 'main' ? '01' : '02');
        };
        form.elements.channel_number.addEventListener('change', updatePath);
        form.elements.preferred_quality.addEventListener('change', updatePath);
        const updateStreamFields = () => {
            const rtc = form.elements.stream_protocol.value === 'webrtc';
            form.elements.stream_url.readOnly = rtc;
            form.elements.rtsp_path.readOnly = rtc;
            if (rtc) updatePath();
        };
        form.elements.stream_protocol.addEventListener('change', updateStreamFields);
        form.addEventListener('invalid', (event) => {
            const panel = event.target.closest('[data-camera-panel]');
            if (panel) tab(panel.dataset.cameraPanel);
        }, true);
        deletion.addEventListener('submit', (event) => {
            if (!window.confirm('¿Eliminar esta cámara? El canal quedará sin configurar.')) event.preventDefault();
        });
        const clockTimer = setInterval(() => {
            root.querySelectorAll('[data-camera-osd]').forEach((node) => {
                const camera = cameras.find((item) => Number(item.id) === Number(node.dataset.cameraOsd));
                node.textContent = [
                    Number(camera?.osd_camera_name) ? camera.name : '',
                    Number(camera?.osd_datetime) ? new Date().toLocaleString('es-PE', { timeZone: 'America/Lima' }) + ' · Hora del visor (Lima)' : ''
                ].filter(Boolean).join(' · ');
                node.hidden = !node.textContent;
            });
        }, 1000);
        webcamVideo.addEventListener('playing', () => {
            if (webcamConnected()) webcamStatus('Conectada');
        });
        webcamVideo.addEventListener('pause', () => {
            if (webcamConnected()) webcamStatus('Imagen pausada');
        });
        webcamVideo.addEventListener('loadeddata', updateWebcamControls);
        webcamVideo.addEventListener('resize', updateWebcamControls);
        webcamVideo.addEventListener('error', () => {
            if (webcamStream) stopWebcam('Sin señal', 'No se pudo mostrar la imagen. Vuelve a conectar la cámara.');
        });
        recording = new window.DeviozWebcamRecorder({
            root, getStream: () => webcamStream,
            isReady: () => webcamConnected() && frameAvailable(webcamVideo) && !webcamVideo.paused,
            onStateChange: updateWebcamControls
        });
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href]');
            if (!link || link.hasAttribute('download') || link.target === '_blank' || event.ctrlKey || event.metaKey) return;
            const url = new URL(link.href, location.href);
            if (url.origin === location.origin && url.pathname === location.pathname && url.search === location.search) return;
            if (recording.preventLeaving()) event.preventDefault();
        }, true);
        window.addEventListener('beforeunload', (event) => {
            if (recording.hasPendingWork()) { event.preventDefault(); event.returnValue = ''; }
        });
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) recording.handleSourceEnd('La pestaña dejó de estar visible.');
        });
        window.addEventListener('pagehide', () => {
            bridgeRequests.forEach(request => request.abort());
            clearInterval(clockTimer); recording.dispose(); stopWebcam(); destroyPlayers();
        });
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) window.location.reload();
        });
        updateWebcamControls();
        find('[data-bridge-enable]').addEventListener('submit', event => {
            if (recording?.preventLeaving()) { event.preventDefault(); return; }
            find('[data-bridge-parking]').value = parking.value;
        });
        find('[data-camera-live-quality]').addEventListener('change', event => {
            if (!selected || selected.stream_protocol !== 'webrtc') return;
            liveQualities.set(Number(selected.id), event.target.value);
            openSingle(selected);
        });
        checkBridge();
        const initialMobileCamera = list().find(camera => camera.status === 'active' && camera.stream_protocol === 'webrtc');
        if (window.matchMedia('(max-width: 767px)').matches && initialMobileCamera) openSingle(initialMobileCamera);
        else renderGrid();
    });
});
