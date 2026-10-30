<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server' || !preg_match('/^devioz_bridge_test_[a-f0-9]{12}$/D', getenv('DB_DATABASE') ?: '')) {
    http_response_code(404);
    exit;
}
// A synthetic upstream error verifies that credentials never reach the browser.
http_response_code(500);
echo 'rtsp://synthetic-user:synthetic-secret@192.168.1.100/Streaming/Channels/102';
