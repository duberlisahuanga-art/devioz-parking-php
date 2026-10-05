<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

if (!is_authenticated()) {
    redirect_to('/frontend/login.php');
}

if (is_admin()) {
    redirect_to('/frontend/admin/dashboard.php');
}

redirect_to('/frontend/conductor/dashboard.php');
