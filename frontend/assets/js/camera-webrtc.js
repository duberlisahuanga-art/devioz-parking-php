/* Authenticated, receive-only WebRTC. No DVR credentials or RTSP URLs in the browser. */
(() => {
    'use strict';
    class CameraWebRTC {
        static pending = [];
        static connecting = 0;
        static connectivity = new Map();
        static enqueue(work) {
            CameraWebRTC.pending.push(work);
            CameraWebRTC.pump();
        }
        static pump() {
            while (CameraWebRTC.connecting < 2 && CameraWebRTC.pending.length) {
                const work = CameraWebRTC.pending.shift();
                CameraWebRTC.connecting++;
                Promise.resolve().then(work).finally(() => {
                    CameraWebRTC.connecting--;
                    CameraWebRTC.pump();
                });
            }
        }
        constructor(options) {
            Object.assign(this, options);
            this.disposed = false;
            this.generation = 0;
            this.retries = 0;
            this.pc = null;
            this.abort = null;
            this.timer = null;
            this.watchdog = null;
            this.metrics = {};
            this.lastFrame = performance.now();
            this.onVisibility = () => {
                if (document.hidden) {
                    this.clear();
                    this.state('Pausado mientras la pestaña está oculta');
                } else this.retry();
            };
            this.onOffline = () => { this.clear(); this.state('Sin conexión de red', 'error'); };
            this.onOnline = () => this.retry();
            document.addEventListener('visibilitychange', this.onVisibility);
            window.addEventListener('offline', this.onOffline);
            window.addEventListener('online', this.onOnline);
            this.start();
        }
        state(message, kind = '') {
            if (!this.disposed) this.onState?.(message, kind);
        }
        clear() {
            this.generation++;
            clearTimeout(this.timer);
            clearInterval(this.watchdog);
            this.timer = this.watchdog = null;
            this.abort?.abort();
            this.abort = null;
            if (this.pc) {
                this.pc.ontrack = this.pc.onconnectionstatechange = null;
                this.pc.close();
                this.pc = null;
            }
            this.video.srcObject?.getTracks().forEach(track => track.stop());
            this.video.srcObject = null;
            this.metrics = {};
        }
        retry() {
            if (this.disposed) return;
            this.retries = 0;
            this.clear();
            this.start();
        }
        fail(message, terminal = false) {
            if (this.disposed) return;
            this.clear();
            if (terminal || this.retries >= 5 || !navigator.onLine) {
                this.state(message + (terminal ? '' : ' Pulsa Reconectar para intentarlo de nuevo.'), 'error');
                return;
            }
            const delay = Math.min(15000, 1000 * 2 ** this.retries++) + Math.floor(Math.random() * 300);
            this.state(`${message} Reintentando (${this.retries}/5)…`, 'error');
            this.timer = setTimeout(() => this.start(), delay);
        }
        start() {
            if (this.disposed || document.hidden || !navigator.onLine) return;
            if (!window.RTCPeerConnection) {
                this.state('Este navegador no permite WebRTC. Utiliza un navegador actualizado.', 'error');
                return;
            }
            const generation = this.generation;
            this.state('Conectando vídeo…');
            CameraWebRTC.enqueue(async () => {
                if (this.disposed || generation !== this.generation || document.hidden) return;
                try {
                    await this.connect(generation);
                } catch (error) {
                    if (this.disposed || generation !== this.generation) return;
                    this.fail(error.message || 'No se pudo conectar el canal.', error.terminal === true);
                }
            });
        }
        async loadConnectivity() {
            const cached = CameraWebRTC.connectivity.get(this.endpoint);
            if (cached && cached.until > Date.now()) return cached.value;
            const controller = new AbortController();
            this.abort = controller;
            const deadline = setTimeout(() => controller.abort(), 4000);
            try {
                const response = await fetch(this.endpoint + '?action=connectivity', {
                    credentials: 'same-origin', cache: 'no-store', signal: controller.signal
                });
                const value = await response.json();
                if (!response.ok) {
                    const error = new Error(value.error || 'No se pudo preparar la conexión del vídeo.');
                    error.terminal = [401, 403, 429].includes(response.status);
                    throw error;
                }
                if (!Array.isArray(value.iceServers) || !['all', 'relay'].includes(value.iceTransportPolicy)) {
                    throw new Error('El servidor devolvió una configuración de vídeo inválida.');
                }
                const until = Math.min(Date.now() + 60000, value.expiresAt * 1000 - 30000);
                CameraWebRTC.connectivity.set(this.endpoint, { until, value });
                return value;
            } finally { clearTimeout(deadline); }
        }
        async connect(generation) {
            const connectivity = await this.loadConnectivity();
            if (this.disposed || generation !== this.generation) return;
            const pc = new RTCPeerConnection({ iceServers: connectivity.iceServers,
                iceTransportPolicy: connectivity.iceTransportPolicy, bundlePolicy: 'max-bundle' });
            this.pc = pc;
            const stream = new MediaStream();
            const transceiver = pc.addTransceiver('video', { direction: 'recvonly' });
            const codecs = window.RTCRtpReceiver?.getCapabilities?.('video')?.codecs || [];
            if (transceiver.setCodecPreferences && codecs.length) {
                transceiver.setCodecPreferences([
                    ...codecs.filter(codec => codec.mimeType.toLowerCase() === 'video/h264'),
                    ...codecs.filter(codec => codec.mimeType.toLowerCase() !== 'video/h264')
                ]);
            }
            pc.ontrack = event => {
                if (this.disposed || this.pc !== pc || event.track.kind !== 'video') return;
                stream.addTrack(event.track);
                this.video.srcObject = stream;
                this.video.play().catch(() => this.state('Pulsa Reproducir para mostrar la imagen'));
            };
            pc.onconnectionstatechange = () => {
                if (this.pc !== pc || this.disposed) return;
                if (pc.connectionState === 'failed') this.fail('Se perdió la conexión con el canal.');
                if (pc.connectionState === 'disconnected') {
                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => {
                        if (this.pc === pc && pc.connectionState === 'disconnected') this.fail('Canal desconectado.');
                    }, 3000);
                }
                if (pc.connectionState === 'connected') clearTimeout(this.timer);
            };
            await pc.setLocalDescription(await pc.createOffer());
            await new Promise(resolve => {
                if (pc.iceGatheringState === 'complete') { resolve(); return; }
                const done = () => { clearTimeout(deadline); pc.removeEventListener('icegatheringstatechange', changed); resolve(); };
                const changed = () => { if (pc.iceGatheringState === 'complete' || this.pc !== pc) done(); };
                const deadline = setTimeout(done, 1800);
                pc.addEventListener('icegatheringstatechange', changed);
            });
            if (this.pc !== pc || generation !== this.generation) return;
            const controller = new AbortController();
            this.abort = controller;
            const deadline = setTimeout(() => controller.abort(), 10000);
            let response;
            let answer;
            try {
                response = await fetch(this.endpoint, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ camera_id: this.cameraId, quality: this.quality,
                        csrf_token: this.csrf, sdp: pc.localDescription.sdp })
                });
                answer = await response.json();
            } finally { clearTimeout(deadline); }
            if (this.pc !== pc || generation !== this.generation) return;
            if (!response.ok) {
                const error = new Error(answer.error || 'No se recibió vídeo del canal.');
                error.terminal = [401, 403, 404, 422, 429].includes(response.status);
                throw error;
            }
            await pc.setRemoteDescription({ type: 'answer', sdp: answer.sdp });
            if (this.pc !== pc || generation !== this.generation) return;
            this.lastFrame = performance.now();
            this.previous = { bytes: 0, frames: 0, time: performance.now(), videoTime: this.video.currentTime };
            this.onlineSince = 0;
            this.lastLease = performance.now();
            this.watchdog = setInterval(() => this.inspect(pc).catch(() => {}), 2500);
        }
        async inspect(pc) {
            if (this.pc !== pc || this.disposed) return;
            const reports = await pc.getStats();
            if (this.pc !== pc || this.disposed) return;
            const now = performance.now();
            let bytes = 0;
            let frames = 0;
            let fps = null;
            let rtt = null;
            reports.forEach(report => {
                if (report.type === 'inbound-rtp' && (report.kind === 'video' || report.mediaType === 'video')) {
                    bytes += report.bytesReceived || 0;
                    frames += report.framesDecoded || 0;
                    fps = report.framesPerSecond ?? fps;
                }
                if (report.type === 'candidate-pair' && report.state === 'succeeded' && report.nominated) {
                    rtt = report.currentRoundTripTime === undefined ? null : Math.round(report.currentRoundTripTime * 1000);
                }
            });
            if (frames > this.previous.frames || this.video.currentTime > this.previous.videoTime) {
                this.lastFrame = now;
                if (!this.onlineSince) this.onlineSince = now;
                if (now - this.onlineSince > 30000) this.retries = 0;
                if (!this.video.paused) this.state('En línea', 'online');
            }
            const elapsed = Math.max(1, now - this.previous.time);
            this.metrics = { kbps: Math.max(0, Math.round((bytes - this.previous.bytes) * 8 / elapsed)),
                fps: fps ?? Math.max(0, Math.round((frames - this.previous.frames) * 1000 / elapsed)), rtt };
            this.previous = { bytes, frames, time: now, videoTime: this.video.currentTime };
            this.onStats?.(this.metrics);
            if ((!this.video.paused || !this.onlineSince) && now - this.lastFrame > 12000) {
                this.fail('No se reciben fotogramas. Revisa H.264, el canal y la red.');
                return;
            }
            if (now - this.lastLease >= 20000) {
                this.lastLease = now;
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 4000);
                try {
                    const response = await fetch(this.endpoint + '?action=status', { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
                    if (this.pc !== pc) return;
                    if ([401, 403].includes(response.status)) this.fail('La sesión terminó. Inicia sesión nuevamente.', true);
                    else if (!response.ok) this.fail('No se pudo validar la sesión de cámaras.');
                } catch {
                    if (this.pc === pc) this.fail('No se pudo validar la conexión con el servidor.');
                } finally { clearTimeout(timeout); }
            }
        }
        dispose() {
            this.disposed = true;
            this.clear();
            document.removeEventListener('visibilitychange', this.onVisibility);
            window.removeEventListener('offline', this.onOffline);
            window.removeEventListener('online', this.onOnline);
        }
    }
    window.DeviozCameraWebRTC = CameraWebRTC;
})();
