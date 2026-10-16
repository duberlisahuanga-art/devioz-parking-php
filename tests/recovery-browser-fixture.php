<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
$root = dirname(__DIR__);
$state = json_decode(file_get_contents($root . '/backend/storage/browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
if (preg_match('/^devioz_browser_[a-f0-9]{12}$/D',$state['database']) !== 1) throw new RuntimeException('Sólo fixture aislado.');
foreach (['DB_DATABASE'=>'database','DB_HOST'=>'host','DB_USERNAME'=>'username','DB_PASSWORD'=>'password'] as $key=>$field) putenv($key . '=' . $state[$field]);
putenv('APP_STORAGE=' . $root . '/backend/storage/' . $state['database'] . '-recovery-tests');
require $root . '/backend/bootstrap.php';
if (($argv[1] ?? '') === 'clean') {
    db()->exec('DELETE FROM password_resets WHERE user_id=2');
    db()->prepare('UPDATE users SET password=? WHERE id=2')->execute([password_hash('Testing123!',PASSWORD_DEFAULT)]);
    exit;
}
PasswordRecoveryService::request('driver@browser.invalid');
$result = '';
PasswordRecoveryService::deliver(function (string $email,string $token) use (&$result): void { $result=$token; });
echo $result;
