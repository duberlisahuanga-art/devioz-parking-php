<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/config/app.php';
header('Location: ' . rtrim(AppConfig::get('APP_BASE_URL', '/devioz-parking-php'), '/') . '/frontend/index.php');
exit;
