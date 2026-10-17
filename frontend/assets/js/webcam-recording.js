/* Grabación manual de webcam. El archivo permanece en memoria hasta confirmar su guardado. */
(() => {
    'use strict';
    class DeviozWebcamRecorder {
        constructor({ root, getStream, isReady, onStateChange }) {
            this.root = root;
            this.find = (selector) => root.querySelector(selector);
            this.getStream = getStream;
            this.isReady = isReady;
            this.onStateChange = onStateChange;
            this.endpoint = root.dataset.recordingsEndpoint;
            this.csrf = root.dataset.recordingsCsrf;
            this.state = 'idle';
            this.limits = { max_duration_ms: 120000, max_bytes: 20 * 1024 * 1024 };
            this.serverReady = false;
            this.disposed = false;
            this.chunks = [];
            this.blob = null;
            this.localUrl = null;
            this.capture = null;
            this.recorder = null;
            this.items = [];
            this.listRequest = 0;
            this.format = this.supportedFormat();
            root.addEventListener('click', (event) => {
                const button = event.target.closest('[data-recording-action]');
                if (!button || !root.contains(button) || button.disabled) return;
                const action = button.dataset.recordingAction;
                if (action === 'start') this.start();
                if (action === 'stop') this.stop();
                if (action === 'retry') this.retrySave();
                if (action === 'download-local') this.downloadLocal();
                if (action === 'discard') this.discard();
                if (action === 'refresh') this.loadList();
            });
            this.find('[data-camera-parking]').addEventListener('change', () => this.loadList());
            this.find('[data-recordings-video]').addEventListener('error', () => {
                this.feedback('No se pudo reproducir el vídeo. Intenta descargarlo para abrirlo con un reproductor compatible.');
            });
            this.render();
            this.loadList();
        }

        supportedFormat() {
            if (!window.MediaRecorder?.isTypeSupported) return null;
            for (const mime of ['video/webm;codecs=vp8', 'video/webm', 'video/mp4']) {
                if (MediaRecorder.isTypeSupported(mime)) return { mime, extension: mime.startsWith('video/webm') ? 'webm' : 'mp4' };
            }
            return null;
        }

        isCapturing() { return ['starting', 'recording', 'stopping'].includes(this.state); }
        hasPendingWork() { return this.state !== 'idle'; }
        preventLeaving() {
            if (!this.hasPendingWork()) return false;
            this.error(this.isCapturing()
                ? 'Detén y guarda la grabación antes de salir de esta vista.'
                : 'Espera el guardado o resuelve la grabación pendiente antes de salir de esta vista.');
            return true;
        }
        error(message = '') {
            const node = this.find('[data-recording-error]');
            node.textContent = message;
            node.hidden = !message;
        }
        feedback(message = '') { this.find('[data-recordings-feedback]').textContent = message; }
        setState(state) {
            this.state = state;
            this.render();
            this.onStateChange?.();
        }
        refreshAvailability() {
            this.find('[data-recording-action="start"]').disabled = this.state !== 'idle' || !this.serverReady || !this.format || !this.isReady();
        }
        render() {
            const labels = { idle: 'Sin grabación en curso', starting: 'Preparando grabación', recording: 'Grabando',
                stopping: 'Finalizando grabación', saving: 'Guardando vídeo en el sistema', unsaved: 'Vídeo pendiente de guardar', discarding: 'Descartando vídeo pendiente' };
            this.find('[data-recording-status]').textContent = labels[this.state];
            this.find('[data-recording-action="stop"]').disabled = this.state !== 'recording';
            this.find('[data-recording-action="retry"]').hidden = this.state !== 'unsaved';
            this.find('[data-recording-action="download-local"]').hidden = !this.blob;
            this.find('[data-recording-discard]').hidden = this.state !== 'unsaved';
            const progress = this.find('[data-recording-progress]');
            progress.hidden = this.state !== 'saving';
            progress.textContent = this.state === 'saving' ? 'Guardando… Mantén esta pestaña abierta hasta recibir la confirmación.' : '';
            this.find('[data-recording-limits]').textContent = this.format
                ? 'Máximo ' + Math.floor(this.limits.max_duration_ms / 60000) + ' minutos o ' + (this.limits.max_bytes / 1048576).toFixed(0) + ' MB por vídeo. Se detendrá automáticamente al alcanzar el límite. Sin audio.'
                : 'Este navegador no admite la grabación de vídeo. Prueba con una versión reciente de Chrome o Edge.';
            this.refreshAvailability();
        }
        parkingId() { return this.find('[data-camera-parking]').value; }
        async request(action, fields = {}, file = null) {
            const url = new URL(this.endpoint, location.href);
            const options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
            if (action === 'list') {
                url.searchParams.set('action', action);
                url.searchParams.set('parking_id', this.parkingId());
            } else {
                const body = new FormData();
                body.set('action', action);
                body.set('csrf_token', this.csrf);
                Object.entries(fields).forEach(([key, value]) => body.set(key, String(value)));
                if (file) body.set('recording', file, 'grabacion.' + this.capture.format);
                options.method = 'POST';
                options.body = body;
            }
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), action === 'save' ? 120000 : 20000);
            options.signal = controller.signal;
            try {
                const response = await fetch(url, options);
                if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                    throw new Error('La sesión terminó o el servidor no está disponible. Inicia sesión de nuevo en otra pestaña y reintenta; conserva esta pestaña para no perder el vídeo.');
                }
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || data.message || 'No se pudo completar la operación. Vuelve a intentar.');
                return data;
            } catch (error) {
                if (error.name === 'AbortError') throw new Error('El servidor tardó demasiado. Vuelve a intentar.');
                if (error instanceof TypeError) throw new Error('No hay conexión con el servidor. Vuelve a intentar.');
                throw error;
            } finally { clearTimeout(timeout); }
        }
        async loadList() {
            const request = ++this.listRequest;
            const parking = this.parkingId();
            if (this.listParking !== parking) {
                this.listParking = parking;
                this.items = [];
                this.drawList();
                this.find('[data-recordings-empty]').hidden = true;
                const player = this.find('[data-recordings-video]');
                player.pause(); player.removeAttribute('src'); player.load();
                this.find('[data-recordings-player]').hidden = true;
            }
            this.feedback('Consultando grabaciones guardadas…');
            try {
                const data = await this.request('list');
                if (this.disposed || request !== this.listRequest) return;
                this.serverReady = true;
                if (typeof data.csrf_token === 'string') this.csrf = data.csrf_token;
                if (data.limits) {
                    this.limits.max_duration_ms = Math.min(120000, Number(data.limits.max_duration_ms) || 120000);
                    this.limits.max_bytes = Math.min(20 * 1048576, Number(data.limits.max_bytes) || 20 * 1048576);
                }
                this.items = data.items || [];
                this.drawList();
                this.feedback(this.items.length ? 'Se muestran hasta 20 grabaciones recientes. Fechas y horas de Lima.' : '');
                this.render();
                return true;
            } catch (error) {
                if (this.disposed || request !== this.listRequest) return;
                this.serverReady = false;
                this.feedback(error.message + ' Pulsa Actualizar grabaciones para volver a intentar.');
                this.render();
                return false;
            }
        }
        async start() {
            if (this.state !== 'idle' || !this.serverReady || !this.format || !this.isReady()) return;
            this.error();
            this.setState('starting');
            this.find('[data-recording-timer]').textContent = '00:00';
            try {
                const stream = this.getStream();
                const recorder = new MediaRecorder(stream, { mimeType: this.format.mime, videoBitsPerSecond: 1200000 });
                const data = await this.request('begin', { parking_id: this.parkingId(), format: this.format.extension });
                this.capture = data.recording;
                if (this.disposed || document.hidden || !stream.getVideoTracks().some((track) => track.readyState === 'live')) {
                    await this.request('cancel', { id: this.capture.id });
                    this.capture = null;
                    if (!this.disposed) throw new Error('No se pudo iniciar: mantén esta pestaña visible y la cámara conectada.');
                    return;
                }
                this.recorder = recorder;
                this.chunks = [];
                this.bytes = 0;
                this.durationMs = null;
                this.started = performance.now();
                recorder.addEventListener('dataavailable', (event) => {
                    if (!event.data.size || this.disposed) return;
                    this.chunks.push(event.data);
                    this.bytes += event.data.size;
                    if (this.bytes >= this.limits.max_bytes * 0.95 && this.state === 'recording') this.stop('Límite de tamaño alcanzado.');
                });
                recorder.addEventListener('stop', () => this.finalize(), { once: true });
                recorder.addEventListener('error', () => {
                    this.error('El navegador interrumpió la grabación. Se intentará conservar el vídeo disponible.');
                    this.stop();
                });
                recorder.start(1000);
                this.setState('recording');
                this.timer = setInterval(() => {
                    const elapsed = performance.now() - this.started;
                    this.find('[data-recording-timer]').textContent = this.duration(elapsed);
                    if (elapsed >= this.limits.max_duration_ms) this.stop('Tiempo máximo alcanzado.');
                }, 200);
            } catch (error) {
                if (this.capture) {
                    this.request('cancel', { id: this.capture.id }).catch(() => {});
                    this.capture = null;
                }
                this.recorder = null;
                if (!this.disposed) { this.setState('idle'); this.error(error.message || 'No se pudo iniciar la grabación. Vuelve a conectar la cámara.'); }
            }
        }
        stop(reason = '') {
            if (this.state !== 'recording' || !this.recorder) return;
            this.durationMs = Math.max(1, Math.round(performance.now() - this.started));
            clearInterval(this.timer);
            this.find('[data-recording-timer]').textContent = this.duration(this.durationMs);
            this.setState('stopping');
            if (reason) this.error(reason + ' Se está guardando el vídeo.');
            if (this.recorder.state !== 'inactive') this.recorder.stop();
        }
        handleSourceEnd(reason = 'La cámara se desconectó.') {
            if (this.state === 'recording') this.stop(reason);
        }
        async finalize() {
            if (this.disposed) return;
            clearInterval(this.timer);
            if (!this.durationMs) this.durationMs = Math.max(1, Math.round(performance.now() - this.started));
            this.blob = new Blob(this.chunks, { type: this.recorder.mimeType });
            this.chunks = [];
            this.recorder = null;
            if (!this.blob.size) {
                await this.request('cancel', { id: this.capture.id }).catch(() => {});
                this.clearLocal();
                this.setState('idle');
                this.error('No se recibió vídeo para guardar. Conecta la cámara e intenta grabar de nuevo.');
                return;
            }
            this.localUrl = URL.createObjectURL(this.blob);
            const preview = this.find('[data-recording-local-preview]');
            preview.src = this.localUrl;
            preview.hidden = false;
            this.setState('unsaved');
            await this.save();
        }
        async save() {
            if (this.state !== 'unsaved' || !this.blob || !this.capture) return;
            if (this.blob.size > this.limits.max_bytes) {
                this.error('El vídeo supera el límite permitido. Descarga una copia y descarta este pendiente; graba un fragmento más corto.');
                return;
            }
            if (this.durationMs > 125000 || this.durationMs < 250) {
                this.error('La duración está fuera del límite de grabación (desde un cuarto de segundo hasta dos minutos). Descarga una copia y descarta el pendiente; graba otro fragmento manteniendo esta pestaña visible.');
                return;
            }
            this.error();
            this.setState('saving');
            try {
                await this.request('save', { id: this.capture.id, duration_ms: this.durationMs }, this.blob);
                if (this.disposed) return;
                this.clearLocal();
                await this.loadList();
                this.setState('idle');
                this.find('[data-recording-status]').textContent = 'Grabación guardada en el sistema';
            } catch (error) {
                if (this.disposed) return;
                this.setState('unsaved');
                this.error(error.message + ' El vídeo permanece en esta pestaña: puedes reintentar o descargar una copia.');
            }
        }
        async retrySave() {
            if (this.state !== 'unsaved') return;
            this.setState('saving');
            const refreshed = await this.loadList();
            if (this.disposed) return;
            this.setState('unsaved');
            if (refreshed) await this.save();
            else this.error('No se pudo renovar la conexión con el sistema. Mantén esta pestaña abierta; puedes descargar una copia y volver a intentar.');
        }
        async discard() {
            if (this.state !== 'unsaved' || !this.capture) return;
            this.setState('discarding');
            try {
                await this.request('cancel', { id: this.capture.id });
                this.clearLocal();
                this.error();
                this.setState('idle');
            } catch (error) {
                // A lost save response may have left a valid recording on the server.
                await this.loadList();
                if (this.items.some((item) => item.id === this.capture?.id)) {
                    this.clearLocal(); this.setState('idle');
                    this.error('El vídeo ya estaba guardado en el sistema; sigue disponible en la lista.');
                } else { this.setState('unsaved'); this.error(error.message); }
            }
        }
        clearLocal() {
            const preview = this.find('[data-recording-local-preview]');
            preview.pause();
            preview.removeAttribute('src');
            preview.load();
            preview.hidden = true;
            if (this.localUrl) URL.revokeObjectURL(this.localUrl);
            this.localUrl = null; this.blob = null; this.capture = null; this.durationMs = null;
            this.find('[data-recording-discard]').open = false;
        }
        downloadLocal() {
            if (!this.blob || !this.localUrl) return;
            const link = document.createElement('a');
            link.href = this.localUrl;
            link.download = 'camara-equipo-' + this.capture.id + '.' + this.capture.format;
            link.click();
        }
        duration(milliseconds) {
            const seconds = Math.floor(milliseconds / 1000);
            return String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
        }
        drawList() {
            const list = this.find('[data-recordings-list]');
            list.replaceChildren();
            this.find('[data-recordings-empty]').hidden = this.items.length > 0;
            const dateFormat = new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', dateStyle: 'short', timeStyle: 'medium' });
            for (const item of this.items) {
                const article = document.createElement('article');
                article.className = 'camera-recording-item';
                article.dataset.recordingItem = item.id;
                const meta = document.createElement('div'); meta.className = 'camera-recording-meta';
                const title = document.createElement('strong');
                title.textContent = 'Cámara del equipo · ' + dateFormat.format(new Date(item.started_at));
                const detail = document.createElement('p');
                detail.textContent = this.duration(item.duration_ms) + ' · ' + (item.byte_size / 1048576).toFixed(2) + ' MB · ' + item.format.toUpperCase();
                meta.append(title, detail);
                const actions = document.createElement('div'); actions.className = 'camera-recording-actions';
                const play = document.createElement('button'); play.type = 'button'; play.className = 'camera-hmi-primary'; play.textContent = 'Reproducir';
                play.addEventListener('click', () => this.play(item));
                const download = document.createElement('a'); download.className = 'btn btn-outline-primary';
                download.textContent = 'Descargar'; download.href = item.download_url; download.download = '';
                actions.append(play, download); article.append(meta, actions); list.append(article);
            }
        }
        play(item) {
            const video = this.find('[data-recordings-video]');
            this.find('[data-recordings-player]').hidden = false;
            this.find('[data-recordings-player-title]').textContent = 'Cámara del equipo · ' + new Date(item.started_at).toLocaleString('es-PE', { timeZone: 'America/Lima' });
            video.src = item.play_url;
            video.load();
            video.play().catch(() => this.feedback('Pulsa el botón de reproducción del vídeo para verlo.'));
        }
        dispose() {
            this.disposed = true;
            clearInterval(this.timer);
            if (this.recorder?.state !== 'inactive') this.recorder?.stop();
            this.clearLocal();
            this.find('[data-recordings-video]').pause();
        }
    }
    window.DeviozWebcamRecorder = DeviozWebcamRecorder;
})();
