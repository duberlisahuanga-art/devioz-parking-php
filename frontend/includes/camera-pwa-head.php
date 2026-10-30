<?php

declare(strict_types=1);

if (!isset($active) || $active !== 'camaras' || !is_admin() || DriverContext::managed() !== null) {
    return;
}
?>
<link rel="manifest" href="<?= h(app_url('/frontend/admin/manifest.webmanifest')) ?>">
<meta name="theme-color" content="#2563eb">
<link rel="apple-touch-icon" sizes="180x180" href="<?= h(app_url('/frontend/assets/pwa/apple-touch-icon.png')) ?>">
<script src="<?= h(app_url('/frontend/assets/js/camera-pwa.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/camera-pwa.js')) ?>" data-camera-pwa data-worker="<?= h(app_url('/frontend/admin/sw.js')) ?>" defer></script>
