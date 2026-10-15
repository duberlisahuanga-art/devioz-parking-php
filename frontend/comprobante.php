<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';
$user = require_auth();
$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$path = $id && $id > 0 ? VoucherService::authorizedPath($user, $type, $id) : null;
if ($path === null) {
    http_response_code(404);
    exit('Comprobante no disponible.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
    http_response_code(415);
    exit('Formato no disponible.');
}
session_write_close();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="comprobante-' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, no-store');
header('Content-Security-Policy: sandbox; default-src \'none\';');
readfile($path);
