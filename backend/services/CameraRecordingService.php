<?php

declare(strict_types=1);

final class CameraRecordingNotFound extends RuntimeException
{
}

final class CameraRecordingUploadTooLarge extends ValidationException
{
}

final class CameraRecordingService
{
    private const MAX_DURATION_MS = 120000;
    private const MAX_BYTES = 20 * 1024 * 1024;
    private const RESERVE_BYTES = 64 * 1024 * 1024;

    /** @return array{max_duration_ms:int,max_bytes:int} */
    public static function limits(): array
    {
        $maximum = self::MAX_BYTES;
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $configured = self::iniBytes((string) ini_get($setting));
            if ($configured > 0) {
                $maximum = min($maximum, max(0, $configured - 65536));
            }
        }
        return ['max_duration_ms' => self::MAX_DURATION_MS, 'max_bytes' => $maximum];
    }

    public static function begin(int $parkingId, string $format): array
    {
        $actor = self::admin();
        self::parking($parkingId);
        if (!in_array($format, ['webm', 'mp4'], true)) {
            throw new ValidationException('El navegador debe grabar vídeo en WebM o MP4.');
        }
        if (self::limits()['max_bytes'] < 65536) {
            throw new ValidationException('El servidor no permite cargar grabaciones. Contacta al administrador.');
        }
        $lock = self::lock();
        try {
            db()->beginTransaction();
            foreach (CameraRecording::expiredPending((int) $actor['id']) as $expired) {
                $path = self::path($expired);
                if (is_file($path) && !unlink($path)) {
                    throw new RuntimeException('Unable to remove expired recording file');
                }
                CameraRecording::deletePending((string) $expired['public_id']);
            }
            if (CameraRecording::pendingCount((int) $actor['id']) >= 3) {
                throw new ValidationException('Ya tienes tres grabaciones pendientes. Guarda o descarta una antes de continuar.');
            }
            self::capacity(0);
            $publicId = bin2hex(random_bytes(16));
            CameraRecording::begin($publicId, $parkingId, (int) $actor['id'], $format);
            $row = self::required($publicId);
            db()->commit();
            return self::dto($row);
        } catch (Throwable $error) {
            self::rollback();
            throw $error;
        } finally {
            self::unlock($lock);
        }
    }

    public static function save(string $publicId, array $file, int $duration): array
    {
        $actor = self::admin();
        self::validateId($publicId);
        $initial = self::required($publicId);
        self::owner($initial, (int) $actor['id']);
        if ($duration < 250 || $duration > self::MAX_DURATION_MS + 5000) {
            throw new ValidationException('La grabación debe durar entre un cuarto de segundo y dos minutos.');
        }
        [$temporary, $bytes, $sha256] = self::upload($file, (string) $initial['format']);
        $lock = self::lock();
        $createdFile = null;
        try {
            db()->beginTransaction();
            $row = self::required($publicId, true);
            self::owner($row, (int) $actor['id']);
            if ($row['state'] === 'ready') {
                if ((int) $row['byte_size'] === $bytes && (int) $row['duration_ms'] === $duration
                    && hash_equals((string) $row['sha256'], $sha256)) {
                    db()->commit();
                    return self::dto($row);
                }
                throw new ValidationException('Esta grabación ya fue guardada. No se puede sustituir su vídeo.');
            }
            $started = new DateTimeImmutable((string) $row['started_at'], new DateTimeZone('America/Lima'));
            $elapsed = (int) round((microtime(true) - (float) $started->format('U.u')) * 1000);
            if ($elapsed > 3600000 || $elapsed < -5000) {
                throw new ValidationException('La grabación pendiente caducó. Inicia una nueva grabación.');
            }
            if ($duration > $elapsed + 5000) {
                throw new ValidationException('La duración recibida no corresponde al inicio de la grabación. Vuelve a intentarlo.');
            }
            self::capacity($bytes);
            $destination = self::path($row);
            if (is_file($destination)) {
                if (filesize($destination) !== $bytes || !hash_equals($sha256, (string) hash_file('sha256', $destination))) {
                    throw new RuntimeException('Recording destination collision');
                }
                // A retry may recover a completed file move after a process interruption.
            } else {
                if (!move_uploaded_file($temporary, $destination)) {
                    throw new RuntimeException('Unable to persist camera recording');
                }
                $createdFile = $destination;
                @chmod($destination, 0600);
            }
            CameraRecording::ready($publicId, $duration, $bytes, $sha256);
            $row = self::required($publicId);
            AuditService::log('Camaras', 'Grabación guardada', 'Grabación de la cámara de este equipo guardada.',
                'CameraRecording', (int) $row['id'], null,
                ['duration_ms' => $duration, 'byte_size' => $bytes, 'source_kind' => 'webcam'], $actor);
            db()->commit();
            return self::dto($row);
        } catch (Throwable $error) {
            self::rollback();
            if ($createdFile !== null && is_file($createdFile)) {
                @unlink($createdFile);
            }
            throw $error;
        } finally {
            self::unlock($lock);
        }
    }

    public static function cancel(string $publicId): void
    {
        $actor = self::admin();
        self::validateId($publicId);
        $lock = self::lock();
        try {
            db()->beginTransaction();
            $row = self::required($publicId, true);
            self::owner($row, (int) $actor['id']);
            if ($row['state'] !== 'pending') {
                throw new ValidationException('El vídeo ya está guardado. Descartar sólo está disponible para grabaciones pendientes.');
            }
            $path = self::path($row);
            // Only this pending record's generated name is eligible for compensation.
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Unable to remove pending recording file');
            }
            CameraRecording::deletePending($publicId);
            db()->commit();
        } catch (Throwable $error) {
            self::rollback();
            throw $error;
        } finally {
            self::unlock($lock);
        }
    }

    public static function recent(int $parkingId): array
    {
        self::admin();
        self::parking($parkingId);
        return array_map(self::dto(...), CameraRecording::recent($parkingId));
    }

    /** @return array{recording:array,path:string} */
    public static function media(string $publicId): array
    {
        self::admin();
        self::validateId($publicId);
        $row = self::required($publicId);
        if ($row['state'] !== 'ready') {
            throw new CameraRecordingNotFound('La grabación todavía no está disponible.');
        }
        $path = self::path($row);
        if (!is_file($path) || filesize($path) !== (int) $row['byte_size']) {
            throw new CameraRecordingNotFound('El archivo de esta grabación no está disponible.');
        }
        return ['recording' => $row, 'path' => $path];
    }

    public static function stream(string $publicId, bool $download): never
    {
        $media = self::media($publicId);
        $row = $media['recording'];
        $size = (int) $row['byte_size'];
        $handle = fopen($media['path'], 'rb');
        if ($handle === false) {
            throw new CameraRecordingNotFound('El archivo de esta grabación no está disponible.');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $start = 0;
        $end = $size - 1;
        $range = $_SERVER['HTTP_RANGE'] ?? null;
        header('Content-Type: video/' . $row['format']);
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Accept-Ranges: bytes');
        $filename = 'grabacion-' . (new DateTimeImmutable((string) $row['started_at']))->format('Ymd-His') . '.' . $row['format'];
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        if ($range !== null) {
            $valid = is_string($range) && preg_match('/^bytes=([0-9]{0,18})-([0-9]{0,18})$/D', $range, $match) === 1
                && ($match[1] !== '' || $match[2] !== '');
            if ($valid) {
                if ($match[1] === '') {
                    $suffix = (int) $match[2];
                    $valid = $suffix > 0;
                    $start = max(0, $size - $suffix);
                } else {
                    $start = (int) $match[1];
                    $end = $match[2] === '' ? $size - 1 : min((int) $match[2], $size - 1);
                    $valid = $start <= $end && $start < $size;
                }
            }
            if (!$valid) {
                fclose($handle);
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                header('Content-Length: 0');
                exit;
            }
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        header('Content-Length: ' . ($end - $start + 1));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            fclose($handle);
            exit;
        }
        if (fseek($handle, $start) !== 0) {
            fclose($handle);
            exit;
        }
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
            $chunk = fread($handle, min($remaining, 65536));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($handle);
        exit;
    }

    /** @return array{0:string,1:int,2:string} */
    private static function upload(array $file, string $format): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new CameraRecordingUploadTooLarge('El vídeo supera el tamaño permitido. Graba un fragmento más corto.');
        }
        if ($error !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_string($file['tmp_name'])
            || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException('No se recibió el vídeo completo. Vuelve a intentar guardarlo.');
        }
        $temporary = $file['tmp_name'];
        $bytes = filesize($temporary);
        if ($bytes === false || $bytes < 64) {
            throw new ValidationException('El vídeo está vacío o incompleto. Inicia una nueva grabación.');
        }
        if ($bytes > self::limits()['max_bytes']) {
            throw new CameraRecordingUploadTooLarge('El vídeo supera el tamaño permitido. Graba un fragmento más corto.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $allowed = $format === 'webm' ? ['video/webm', 'video/x-matroska'] : ['video/mp4', 'video/quicktime'];
        if (!in_array($mime, $allowed, true)) {
            throw new ValidationException('El archivo recibido no es un vídeo WebM o MP4 válido.');
        }
        $contents = file_get_contents($temporary);
        if ($contents === false || !($format === 'webm' ? self::webm($contents) : self::mp4($contents))) {
            throw new ValidationException('El vídeo no contiene una pista de imagen compatible o está incompleto.');
        }
        $sha256 = hash('sha256', $contents);
        return [$temporary, $bytes, $sha256];
    }

    private static function webm(string $data): bool
    {
        $offset = 0;
        $header = self::ebmlElement($data, $offset, strlen($data));
        if ($header === null || $header['id'] !== 0x1a45dfa3 || $header['unknown']) {
            return false;
        }
        $documentType = false;
        $child = $header['start'];
        while ($child < $header['end']) {
            $element = self::ebmlElement($data, $child, $header['end']);
            if ($element === null || $element['unknown']) {
                return false;
            }
            if ($element['id'] === 0x4282) {
                $documentType = substr($data, $element['start'], $element['end'] - $element['start']) === 'webm';
            }
        }
        if (!$documentType) {
            return false;
        }
        $segment = self::ebmlElement($data, $offset, strlen($data));
        if ($segment === null || $segment['id'] !== 0x18538067) {
            return false;
        }
        $child = $segment['start'];
        $video = false;
        $cluster = false;
        $count = 0;
        while ($child < $segment['end'] && ++$count < 10000) {
            $element = self::ebmlElement($data, $child, $segment['end']);
            if ($element === null) {
                return false;
            }
            if ($element['id'] === 0x1654ae6b) {
                $video = self::webmVideoTrack($data, $element['start'], $element['end']);
            }
            if ($element['id'] === 0x1f43b675) {
                $cluster = self::webmHasBlock($data, $element['start'], $element['end']);
                if ($video && $cluster) {
                    return true;
                }
            }
            if ($element['unknown']) {
                break;
            }
        }
        return false;
    }

    private static function webmHasBlock(string $data, int $offset, int $end): bool
    {
        $count = 0;
        while ($offset < $end && ++$count < 10000) {
            $element = self::ebmlElement($data, $offset, $end);
            if ($element === null || $element['unknown']) {
                return false;
            }
            if ($element['id'] === 0xa3 && $element['end'] - $element['start'] > 4) {
                return true;
            }
            if ($element['id'] === 0xa0) {
                $position = $element['start'];
                while ($position < $element['end']) {
                    $block = self::ebmlElement($data, $position, $element['end']);
                    if ($block === null || $block['unknown']) {
                        return false;
                    }
                    if ($block['id'] === 0xa1 && $block['end'] - $block['start'] > 4) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private static function webmVideoTrack(string $data, int $offset, int $end): bool
    {
        $count = 0;
        while ($offset < $end && ++$count < 1000) {
            $entry = self::ebmlElement($data, $offset, $end);
            if ($entry === null || $entry['unknown']) {
                return false;
            }
            if ($entry['id'] !== 0xae) {
                continue;
            }
            $position = $entry['start'];
            $video = false;
            $codec = false;
            while ($position < $entry['end']) {
                $field = self::ebmlElement($data, $position, $entry['end']);
                if ($field === null || $field['unknown']) {
                    return false;
                }
                $value = substr($data, $field['start'], $field['end'] - $field['start']);
                if ($field['id'] === 0x83) {
                    $video = $value === "\x01";
                }
                if ($field['id'] === 0x86) {
                    $codec = in_array($value, ['V_VP8', 'V_VP9', 'V_AV1'], true);
                }
            }
            if ($video && $codec) {
                return true;
            }
        }
        return false;
    }

    /** @return array{id:int,start:int,end:int,unknown:bool}|null */
    private static function ebmlElement(string $data, int &$offset, int $end): ?array
    {
        $id = self::ebmlInteger($data, $offset, $end, true);
        $size = self::ebmlInteger($data, $offset, $end, false);
        if ($id === null || $size === null) {
            return null;
        }
        $unknown = $size < 0;
        $elementEnd = $unknown ? $end : $offset + $size;
        if ($elementEnd > $end || $elementEnd < $offset) {
            return null;
        }
        $element = ['id' => $id, 'start' => $offset, 'end' => $elementEnd, 'unknown' => $unknown];
        $offset = $elementEnd;
        return $element;
    }

    private static function ebmlInteger(string $data, int &$offset, int $end, bool $identifier): ?int
    {
        if ($offset >= $end) {
            return null;
        }
        $first = ord($data[$offset]);
        $mask = 0x80;
        $length = 1;
        while ($length <= 8 && ($first & $mask) === 0) {
            ++$length;
            $mask >>= 1;
        }
        if ($length > ($identifier ? 4 : 8) || $offset + $length > $end) {
            return null;
        }
        $value = $identifier ? $first : ($first & ($mask - 1));
        $unknown = !$identifier && $value === $mask - 1;
        for ($index = 1; $index < $length; ++$index) {
            $byte = ord($data[$offset + $index]);
            $value = ($value << 8) | $byte;
            $unknown = $unknown && $byte === 255;
        }
        $offset += $length;
        return $unknown ? -1 : $value;
    }

    private static function mp4(string $data): bool
    {
        $boxes = self::mp4Boxes($data, 0, strlen($data));
        if ($boxes === null) {
            return false;
        }
        $brand = false;
        $video = false;
        $media = false;
        foreach ($boxes as $box) {
            if ($box['type'] === 'ftyp' && $box['end'] - $box['start'] >= 8) {
                $brands = substr($data, $box['start'], 4) . substr($data, $box['start'] + 8, $box['end'] - $box['start'] - 8);
                foreach (str_split($brands, 4) as $candidate) {
                    $brand = $brand || in_array($candidate, ['isom', 'iso2', 'iso5', 'iso6', 'mp41', 'mp42', 'avc1', 'hvc1'], true);
                }
            }
            if ($box['type'] === 'moov') {
                $video = self::mp4VideoTrack($data, $box['start'], $box['end']);
            }
            if ($box['type'] === 'mdat') {
                $media = $box['end'] > $box['start'];
            }
        }
        return $brand && $video && $media;
    }

    private static function mp4VideoTrack(string $data, int $offset, int $end): bool
    {
        $boxes = self::mp4Boxes($data, $offset, $end);
        if ($boxes === null) {
            return false;
        }
        foreach ($boxes as $track) {
            if ($track['type'] !== 'trak') {
                continue;
            }
            $children = self::mp4Boxes($data, $track['start'], $track['end']) ?? [];
            foreach ($children as $child) {
                if ($child['type'] !== 'mdia') {
                    continue;
                }
                $media = self::mp4Boxes($data, $child['start'], $child['end']) ?? [];
                $video = false;
                $codec = false;
                foreach ($media as $field) {
                    if ($field['type'] === 'hdlr' && $field['end'] - $field['start'] >= 12
                        && substr($data, $field['start'] + 8, 4) === 'vide') {
                        $video = true;
                    }
                    if ($field['type'] === 'minf') {
                        $codec = self::mp4Codec($data, $field['start'], $field['end']);
                    }
                }
                if ($video && $codec) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function mp4Codec(string $data, int $offset, int $end): bool
    {
        foreach (self::mp4Boxes($data, $offset, $end) ?? [] as $box) {
            if ($box['type'] !== 'stbl') {
                continue;
            }
            foreach (self::mp4Boxes($data, $box['start'], $box['end']) ?? [] as $sampleTable) {
                if ($sampleTable['type'] !== 'stsd' || $sampleTable['end'] - $sampleTable['start'] < 8) {
                    continue;
                }
                foreach (self::mp4Boxes($data, $sampleTable['start'] + 8, $sampleTable['end']) ?? [] as $entry) {
                    if (in_array($entry['type'], ['avc1', 'avc3', 'hvc1', 'hev1', 'av01', 'vp09'], true)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /** @return list<array{type:string,start:int,end:int}>|null */
    private static function mp4Boxes(string $data, int $offset, int $end): ?array
    {
        $boxes = [];
        while ($offset < $end && count($boxes) < 10000) {
            if ($end - $offset < 8) {
                return null;
            }
            $size = unpack('Nsize', substr($data, $offset, 4))['size'];
            $type = substr($data, $offset + 4, 4);
            $header = 8;
            if ($size === 1) {
                if ($end - $offset < 16 || substr($data, $offset + 8, 4) !== "\0\0\0\0") {
                    return null;
                }
                $size = unpack('Nsize', substr($data, $offset + 12, 4))['size'];
                $header = 16;
            } elseif ($size === 0) {
                $size = $end - $offset;
            }
            if ($size < $header || $size > $end - $offset) {
                return null;
            }
            $boxes[] = ['type' => $type, 'start' => $offset + $header, 'end' => $offset + $size];
            $offset += $size;
        }
        return $offset === $end ? $boxes : null;
    }

    private static function admin(): array
    {
        require_admin();
        $actor = current_user();
        if ($actor === null) {
            throw new RuntimeException('Administrator session required');
        }
        return $actor;
    }

    private static function parking(int $id): void
    {
        if ($id < 1) {
            throw new ValidationException('Selecciona un estacionamiento válido.');
        }
        $statement = db()->prepare('SELECT id FROM parkings WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) {
            throw new ValidationException('El estacionamiento seleccionado no existe.');
        }
    }

    private static function validateId(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new CameraRecordingNotFound('La grabación no existe o no está disponible.');
        }
    }

    private static function required(string $id, bool $lock = false): array
    {
        $row = CameraRecording::find($id, $lock);
        if ($row === null) {
            throw new CameraRecordingNotFound('La grabación no existe o no está disponible.');
        }
        return $row;
    }

    private static function owner(array $row, int $actor): void
    {
        if ((int) $row['created_by'] !== $actor) {
            throw new CameraRecordingNotFound('La grabación no existe o no está disponible.');
        }
    }

    private static function dto(array $row): array
    {
        $id = (string) $row['public_id'];
        $zone = new DateTimeZone('America/Lima');
        return ['id' => $id, 'parking_id' => (int) $row['parking_id'], 'state' => $row['state'],
            'format' => $row['format'],
            'started_at' => (new DateTimeImmutable((string) $row['started_at'], $zone))->format('Y-m-d\TH:i:s.vP'),
            'ended_at' => $row['ended_at'] === null ? null : (new DateTimeImmutable((string) $row['ended_at'], $zone))->format('Y-m-d\TH:i:s.vP'),
            'duration_ms' => $row['duration_ms'] === null ? null : (int) $row['duration_ms'],
            'byte_size' => (int) $row['byte_size'],
            'play_url' => app_url('frontend/admin/grabaciones.php?action=play&id=' . $id),
            'download_url' => app_url('frontend/admin/grabaciones.php?action=download&id=' . $id)];
    }

    private static function root(): string
    {
        $directory = rtrim(AppConfig::get('CAMERA_RECORDINGS_PATH', AppConfig::storage() . '/camera-recordings'), '/\\');
        if ($directory === '') {
            throw new RuntimeException('Recording storage is not configured');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Recording storage unavailable');
        }
        $root = realpath($directory);
        if ($root === false || !is_writable($root)) {
            throw new RuntimeException('Recording storage unavailable');
        }
        // Defense in depth if storage is placed beneath an Apache document root.
        $deny = $root . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($deny) && file_put_contents($deny, "Require all denied\n", LOCK_EX) === false) {
            throw new RuntimeException('Recording storage protection unavailable');
        }
        return $root;
    }

    private static function path(array $row): string
    {
        self::validateId((string) $row['public_id']);
        if (!in_array($row['format'], ['webm', 'mp4'], true)) {
            throw new RuntimeException('Unexpected recording format');
        }
        return self::root() . DIRECTORY_SEPARATOR . $row['public_id'] . '.' . $row['format'];
    }

    private static function capacity(int $bytes): void
    {
        $configured = AppConfig::get('CAMERA_RECORDINGS_QUOTA_MB', '1024');
        $quotaMb = filter_var($configured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1048576]]);
        if ($quotaMb === false) {
            throw new RuntimeException('Invalid camera recording quota');
        }
        if (CameraRecording::totalBytes() + $bytes >= $quotaMb * 1024 * 1024) {
            throw new ValidationException('Se alcanzó el espacio asignado a las grabaciones. Contacta al administrador.');
        }
        $free = disk_free_space(self::root());
        if ($free === false || $free < $bytes + self::RESERVE_BYTES) {
            throw new ValidationException('No hay suficiente espacio disponible para guardar vídeo. Contacta al administrador.');
        }
    }

    private static function lock(): string
    {
        $database = (string) db()->query('SELECT DATABASE()')->fetchColumn();
        $name = 'devioz-recordings-' . substr(hash('sha256', $database), 0, 32);
        $statement = db()->prepare('SELECT GET_LOCK(:name, 5)');
        $statement->execute(['name' => $name]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new ValidationException('Hay otra grabación guardándose. Espera unos segundos y vuelve a intentarlo.');
        }
        return $name;
    }

    private static function unlock(string $name): void
    {
        $statement = db()->prepare('SELECT RELEASE_LOCK(:name)');
        $statement->execute(['name' => $name]);
    }

    private static function rollback(): void
    {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }
        $number = (float) $value;
        $unit = strtolower(substr($value, -1));
        return (int) ($number * match ($unit) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 });
    }
}
