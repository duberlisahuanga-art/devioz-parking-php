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
            this.libraryBusy = false;
            this.days = [];
            this.hours = [];
            this.total = 0;
            this.dayTotal = 0;
            const remembered = new URL(location.href).searchParams;
            const savedParking = remembered.get('recordings_parking');
            if ([...this.find('[data-camera-parking]').options].some(option => option.value === savedParking)) {
                this.find('[data-camera-parking]').value = savedParking;
            }
            this.libraryMonth = this.validMonth(remembered.get('recordings_month')) ? remembered.get('recordings_month') : null;
            this.selectedDate = this.validDate(remembered.get('recordings_date')) ? remembered.get('recordings_date') : null;
            const hour = remembered.get('recordings_hour');
            this.selectedHour = hour !== null && /^(?:[0-9]|1[0-9]|2[0-3])$/.test(hour) ? Number(hour) : null;
            this.page = Math.max(1, Math.min(100000, Math.trunc(Number(remembered.get('recordings_page'))) || 1));
            this.format = this.supportedFormat();
            root.addEventListener('click', (event) => {
                const button = event.target.closest('[data-recording-action]');
                if (!button || !root.contains(button) || button.disabled) return;
                const action = button.dataset.recordingAction;
                if (['previous-month', 'next-month', 'previous-page', 'next-page'].includes(action)) this.rememberFocus('[data-recording-action="' + action + '"]');
                if (action === 'start') this.start();
                if (action === 'stop') this.stop();
                if (action === 'retry') this.retrySave();
                if (action === 'download-local') this.downloadLocal();
                if (action === 'discard') this.discard();
                if (action === 'refresh') this.loadList();
                if (action === 'previous-month') this.shiftMonth(-1);
                if (action === 'next-month') this.shiftMonth(1);
                if (action === 'previous-page' && this.page > 1) { this.page--; this.loadDay(); }
                if (action === 'next-page' && this.hasMore) { this.page++; this.loadDay(); }
            });
            root.addEventListener('click', (event) => {
                const day = event.target.closest('[data-recording-date]');
                const hour = event.target.closest('[data-recording-hour]');
                if (this.libraryBusy || (!day && !hour)) return;
                if (day && root.contains(day)) {
                    this.rememberFocus('[data-recording-date="' + day.dataset.recordingDate + '"]');
                    this.selectedDate = day.dataset.recordingDate;
                    this.selectedHour = null;
                    this.drawCalendar();
                } else if (hour && root.contains(hour)) {
                    this.rememberFocus('[data-recording-hour="' + hour.dataset.recordingHour + '"]');
                    this.selectedHour = hour.dataset.recordingHour === 'all' ? null : Number(hour.dataset.recordingHour);
                } else return;
                this.page = 1;
                this.loadDay();
            });
            this.find('[data-recordings-month]').addEventListener('change', (event) => {
                if (!this.validMonth(event.target.value)) {
                    this.calendarError('Selecciona un mes válido.');
                    event.target.value = this.libraryMonth || '';
                    return;
                }
                this.rememberFocus('[data-recordings-month]');
                this.selectedHour = null; this.page = 1;
                this.loadList({ month: event.target.value, date: null });
            });
            this.find('[data-camera-parking]').addEventListener('change', () => this.loadList());
            this.find('[data-recordings-video]').addEventListener('error', () => {
                if (!this.find('[data-recordings-player]').hidden && this.playbackUrl === this.find('[data-recordings-video]').src) {
                    this.feedback('No se pudo reproducir el vídeo. Intenta descargarlo para abrirlo con un reproductor compatible.');
                }
            });
            document.addEventListener('focusin', (event) => {
                if (this.libraryBusy && this.focusReturn && event.target !== this.focusReturn.origin
                    && event.target !== document.body && event.target !== document.documentElement) this.focusReturn.cancelled = true;
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
            if (action === 'list' || action === 'calendar') {
                url.searchParams.set('action', action);
                url.searchParams.set('parking_id', this.parkingId());
                Object.entries(fields).forEach(([key, value]) => {
                    if (value !== null && value !== undefined) url.searchParams.set(key, String(value));
                });
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
        validMonth(value) { return typeof value === 'string' && /^[1-9][0-9]{3}-(?:0[1-9]|1[0-2])$/.test(value) && Number(value.slice(0, 4)) <= 9998; }
        validDate(value) {
            if (typeof value !== 'string' || !/^[1-9][0-9]{3}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])$/.test(value)) return false;
            const date = new Date(value + 'T12:00:00-05:00');
            return this.validMonth(value.slice(0, 7)) && !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
        }
        limaToday() { return new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/Lima' }).format(new Date()); }
        dateLabel(value) {
            return new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(value + 'T12:00:00-05:00'));
        }
        calendarError(message = '') {
            const node = this.find('[data-recordings-calendar-error]');
            node.textContent = message; node.hidden = !message;
        }
        setLibraryBusy(busy) {
            this.libraryBusy = busy;
            this.find('[data-recordings-month]').disabled = busy;
            this.find('[data-recordings-month]').min = '1000-01';
            this.find('[data-recordings-month]').max = '9998-12';
            this.root.querySelectorAll('[data-recording-date],[data-recording-hour]').forEach(node => { node.disabled = busy; });
            for (const action of ['refresh', 'previous-month', 'next-month', 'previous-page', 'next-page']) {
                const node = this.find('[data-recording-action="' + action + '"]');
                node.disabled = busy || (action === 'previous-page' && this.page <= 1) || (action === 'next-page' && !this.hasMore)
                    || (action === 'previous-month' && this.libraryMonth === '1000-01') || (action === 'next-month' && this.libraryMonth === '9998-12');
            }
            if (!busy && this.focusReturn) {
                const focus = this.focusReturn; this.focusReturn = null;
                if (!focus.cancelled && (document.activeElement === document.body || document.activeElement === focus.origin)) {
                    const target = this.find(focus.selector);
                    if (target && !target.disabled) target.focus({ preventScroll: true });
                    else if (focus.selector.includes('next-page') || focus.selector.includes('previous-page')) {
                        const fallback = this.find('[data-recording-action="' + (focus.selector.includes('next-page') ? 'previous-page' : 'next-page') + '"]');
                        if (!fallback.disabled) fallback.focus({ preventScroll: true });
                        else { const summary = this.find('[data-recordings-summary]'); summary.tabIndex = -1; summary.focus({ preventScroll: true }); }
                    }
                }
            }
        }
        rememberFocus(selector) { this.focusReturn = { selector, origin: document.activeElement, cancelled: false }; }
        clearDayView() {
            this.playbackUrl = null;
            this.items = []; this.drawList();
            this.find('[data-recordings-empty]').hidden = true;
            this.find('[data-recordings-summary]').textContent = '';
            this.find('[data-recordings-pagination]').hidden = true;
            const player = this.find('[data-recordings-video]');
            player.pause(); player.removeAttribute('src'); player.load();
            this.find('[data-recordings-player]').hidden = true;
        }
        rememberLibrary() {
            if (this.find('[data-camera-view="recordings"]').hidden && this.find('[data-camera-view="webcam"]').hidden) return;
            const url = new URL(location.href);
            const fields = { recordings_parking: this.parkingId(), recordings_month: this.libraryMonth,
                recordings_date: this.selectedDate, recordings_hour: this.selectedHour, recordings_page: this.page };
            for (const [key, value] of Object.entries(fields)) {
                if (value === null) url.searchParams.delete(key);
                else url.searchParams.set(key, String(value));
            }
            history.replaceState(history.state, '', url);
        }
        acceptContext(data) {
            this.serverReady = true;
            if (typeof data.csrf_token === 'string') this.csrf = data.csrf_token;
            if (data.limits) {
                this.limits.max_duration_ms = Math.min(120000, Number(data.limits.max_duration_ms) || 120000);
                this.limits.max_bytes = Math.min(20 * 1048576, Number(data.limits.max_bytes) || 20 * 1048576);
            }
        }
        async loadList(options = {}) {
            const request = ++this.listRequest;
            const parking = this.parkingId();
            if (this.listParking && this.listParking !== parking) {
                this.libraryMonth = null; this.selectedDate = null; this.selectedHour = null; this.page = 1;
            }
            this.listParking = parking;
            const month = Object.hasOwn(options, 'month') ? options.month : this.libraryMonth;
            const date = Object.hasOwn(options, 'date') ? options.date : this.selectedDate;
            this.libraryMonth = month; this.selectedDate = date;
            this.find('[data-recordings-month]').value = month || '';
            this.find('[data-recordings-month-title]').textContent = month
                ? new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', month: 'long', year: 'numeric' }).format(new Date(month + '-01T12:00:00-05:00')) : 'Calendario de grabaciones';
            this.setLibraryBusy(true); this.calendarError(); this.clearDayView();
            this.find('[data-recordings-days]').replaceChildren();
            this.find('[data-recordings-hours]').replaceChildren();
            this.find('[data-recordings-selected-date]').textContent = 'Consultando día…';
            this.find('[data-recordings-calendar-status]').textContent = 'Consultando calendario…';
            this.feedback('Consultando grabaciones guardadas…');
            try {
                const data = await this.request('calendar', { month });
                if (this.disposed || request !== this.listRequest) return;
                this.acceptContext(data);
                this.libraryMonth = data.month;
                this.days = data.days;
                if (date?.startsWith(data.month) && this.validDate(date)) this.selectedDate = date;
                else {
                    this.selectedDate = data.latest_date?.startsWith(data.month) ? data.latest_date
                        : (data.days.at(-1)?.date || (this.limaToday().startsWith(data.month) ? this.limaToday() : data.month + '-01'));
                    this.page = 1;
                }
                this.drawCalendar();
                return await this.loadDay(request);
            } catch (error) {
                if (this.disposed || request !== this.listRequest) return false;
                this.serverReady = false;
                this.calendarError(error.message + ' Pulsa Actualizar grabaciones para volver a intentar.');
                this.find('[data-recordings-calendar-status]').textContent = 'No se pudo consultar el calendario.';
                this.find('[data-recordings-selected-date]').textContent = 'Consulta no disponible';
                this.feedback('');
                return false;
            } finally {
                if (request === this.listRequest && !this.disposed) { this.setLibraryBusy(false); this.render(); }
            }
        }
        async loadDay(existingRequest = null) {
            const request = existingRequest ?? ++this.listRequest;
            this.setLibraryBusy(true); this.clearDayView();
            this.find('[data-recordings-selected-date]').textContent = this.dateLabel(this.selectedDate);
            this.find('[data-recordings-hours]').replaceChildren();
            this.feedback('Consultando vídeos del día…');
            try {
                const data = await this.request('list', { date: this.selectedDate, hour: this.selectedHour, page: this.page });
                if (this.disposed || request !== this.listRequest) return false;
                this.acceptContext(data);
                const lastPage = Math.max(1, Math.ceil(data.total / data.page_size));
                if (this.followLastPage) {
                    this.followLastPage = false;
                    if (this.page !== lastPage) { this.page = lastPage; return await this.loadDay(request); }
                }
                if (this.page > lastPage) { this.page = lastPage; return await this.loadDay(request); }
                this.items = data.items || [];
                this.total = data.total; this.dayTotal = data.day_total;
                this.hours = data.hours; this.hasMore = data.has_more; this.pageSize = data.page_size;
                this.drawHours();
                this.drawList();
                this.drawPagination(); this.rememberLibrary();
                this.feedback('');
                return true;
            } catch (error) {
                if (this.disposed || request !== this.listRequest) return false;
                this.serverReady = false;
                this.feedback(error.message + ' Pulsa Actualizar grabaciones para volver a intentar. No se ha confirmado que este día esté vacío.');
                return false;
            } finally {
                if (request === this.listRequest && !this.disposed) { this.setLibraryBusy(false); this.render(); }
            }
        }
        shiftMonth(delta) {
            if (!this.libraryMonth || this.libraryBusy) return;
            const [year, month] = this.libraryMonth.split('-').map(Number);
            const next = new Date(Date.UTC(year, month - 1 + delta, 1)).toISOString().slice(0, 7);
            if (!this.validMonth(next)) return;
            this.selectedHour = null; this.page = 1;
            this.loadList({ month: next, date: null });
        }
        drawCalendar() {
            const container = this.find('[data-recordings-days]'); container.replaceChildren();
            this.find('[data-recordings-month]').value = this.libraryMonth;
            const first = new Date(this.libraryMonth + '-01T12:00:00-05:00');
            this.find('[data-recordings-month-title]').textContent = new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', month: 'long', year: 'numeric' }).format(first);
            const [year, month] = this.libraryMonth.split('-').map(Number);
            const daysInMonth = new Date(Date.UTC(year, month, 0)).getUTCDate();
            for (let index = 0; index < (first.getUTCDay() + 6) % 7; index++) {
                const placeholder = document.createElement('span'); placeholder.className = 'camera-library__day-placeholder'; placeholder.setAttribute('aria-hidden', 'true'); container.append(placeholder);
            }
            for (let day = 1; day <= daysInMonth; day++) {
                const date = this.libraryMonth + '-' + String(day).padStart(2, '0');
                const count = this.days.find(item => item.date === date)?.count || 0;
                const button = document.createElement('button'); button.type = 'button';
                button.className = 'camera-library__day' + (count ? ' has-recordings' : '');
                button.dataset.recordingDate = date; button.setAttribute('aria-pressed', String(date === this.selectedDate));
                button.setAttribute('aria-label', this.dateLabel(date) + ', ' + count + (count === 1 ? ' vídeo' : ' vídeos'));
                const number = document.createElement('span'); number.className = 'camera-library__day-number'; number.textContent = String(day);
                const amount = document.createElement('span'); amount.className = 'camera-library__day-count'; amount.textContent = count ? String(count) : '—';
                button.append(number, amount); container.append(button);
            }
            this.find('[data-recordings-calendar-status]').textContent = this.days.length
                ? this.days.length + ' días con vídeos guardados. Elige un día para ver sus horas.' : 'Este mes no tiene grabaciones guardadas.';
        }
        drawHours() {
            const container = this.find('[data-recordings-hours]'); container.replaceChildren();
            const entries = [{ hour: null, count: this.dayTotal }, ...this.hours];
            for (const entry of entries) {
                const button = document.createElement('button'); button.type = 'button';
                button.className = 'camera-library__hour' + (entry.hour === null ? ' camera-library__hour--all' : '');
                button.dataset.recordingHour = entry.hour === null ? 'all' : String(entry.hour);
                button.setAttribute('aria-pressed', String(entry.hour === this.selectedHour));
                const label = entry.hour === null ? 'Todas las horas' : String(entry.hour).padStart(2, '0') + ':00';
                button.setAttribute('aria-label', label + ', ' + entry.count + (entry.count === 1 ? ' vídeo' : ' vídeos'));
                button.append(document.createTextNode(label));
                const count = document.createElement('span'); count.className = 'camera-library__hour-count'; count.textContent = String(entry.count);
                button.append(count); container.append(button);
            }
        }
        drawPagination() {
            const from = this.total ? (this.page - 1) * this.pageSize + 1 : 0;
            const to = Math.min(this.page * this.pageSize, this.total);
            this.find('[data-recordings-summary]').textContent = this.total
                ? 'Mostrando ' + from + '–' + to + ' de ' + this.total + ' vídeos' + (this.selectedHour === null ? ' del día.' : ' en la hora ' + String(this.selectedHour).padStart(2, '0') + ':00.')
                : (this.selectedHour === null ? 'No hay vídeos guardados para este día.' : 'No hay vídeos guardados en esta hora.');
            this.find('[data-recordings-empty]').textContent = this.selectedHour === null ? 'No hay grabaciones guardadas para este día.' : 'Esta hora no tiene grabaciones guardadas.';
            this.find('[data-recordings-pagination]').hidden = this.total <= this.pageSize;
            this.find('[data-recordings-page]').textContent = 'Página ' + this.page + ' de ' + Math.max(1, Math.ceil(this.total / this.pageSize));
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
                const saved = await this.request('save', { id: this.capture.id, duration_ms: this.durationMs }, this.blob);
                if (this.disposed) return;
                const date = saved.recording.started_at.slice(0, 10);
                this.selectedHour = null; this.page = 1; this.followLastPage = true;
                this.clearLocal();
                await this.loadList({ month: date.slice(0, 7), date });
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
            const capture = this.capture;
            try {
                await this.request('cancel', { id: this.capture.id });
                this.clearLocal();
                this.error();
                this.setState('idle');
            } catch (error) {
                // A lost save response may have left a valid recording on the server.
                if (await this.storedCopyExists(capture.id, this.blob.size)) {
                    this.clearLocal();
                    this.selectedHour = null; this.page = 1; this.followLastPage = true;
                    await this.loadList({ month: capture.started_at.slice(0, 7), date: capture.started_at.slice(0, 10) });
                    this.setState('idle');
                    this.error('El vídeo ya estaba guardado en el sistema; sigue disponible en la lista.');
                } else { this.setState('unsaved'); this.error(error.message); }
            }
        }
        async storedCopyExists(id, size) {
            const url = new URL(this.endpoint, location.href);
            url.searchParams.set('action', 'play'); url.searchParams.set('id', id);
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 20000);
            try {
                const response = await fetch(url, { method: 'HEAD', credentials: 'same-origin', signal: controller.signal });
                return response.ok && !response.redirected && response.headers.get('content-type')?.startsWith('video/')
                    && Number(response.headers.get('content-length')) === size;
            } catch { return false; } finally { clearTimeout(timeout); }
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
            const timeFormat = new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' });
            for (const item of this.items) {
                const article = document.createElement('article');
                article.className = 'camera-recording-item';
                article.dataset.recordingItem = item.id;
                const meta = document.createElement('div'); meta.className = 'camera-recording-meta';
                const title = document.createElement('strong');
                title.textContent = 'Cámara del equipo · ' + dateFormat.format(new Date(item.started_at));
                const detail = document.createElement('p');
                const ending = item.ended_at?.slice(0, 10) === item.started_at.slice(0, 10)
                    ? timeFormat.format(new Date(item.ended_at)) : dateFormat.format(new Date(item.ended_at));
                detail.textContent = 'Hasta ' + ending + ' · ' + this.duration(item.duration_ms) + ' · ' + (item.byte_size / 1048576).toFixed(2) + ' MB · ' + item.format.toUpperCase();
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
            const url = video.src; this.playbackUrl = url;
            video.load();
            const title = this.find('[data-recordings-player-title]');
            title.tabIndex = -1; title.focus({ preventScroll: true });
            this.find('[data-recordings-player]').scrollIntoView({ block: 'nearest', behavior: 'instant' });
            video.play().catch(error => {
                if (error.name !== 'AbortError' && this.playbackUrl === url && !this.find('[data-recordings-player]').hidden) this.feedback('Pulsa el botón de reproducción del vídeo para verlo.');
            });
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
