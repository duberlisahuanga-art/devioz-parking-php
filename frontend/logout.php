<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';
AuthController::logout();
redirect_to('/frontend/login.php');
