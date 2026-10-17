<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$path=rawurldecode($path?:'/');
$path=preg_replace('~^/devioz-parking-php~','',$path);
if (preg_match('~(^|/)\.|^/(?:database|docs|tests|tools|vendor|node_modules)(/|$)|^/backend/(?:uploads|storage|config|models|controllers|services|includes)(/|$)~i',$path)) {
    http_response_code(403); exit;
}
$state=json_decode(file_get_contents($root.'/backend/storage/browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
foreach (['DB_DATABASE'=>'database','DB_HOST'=>'host','DB_USERNAME'=>'username','DB_PASSWORD'=>'password'] as $key=>$field) putenv($key.'='.$state[$field]);
putenv('APP_ENV=testing');
$testScope = $_SERVER['HTTP_X_DEVIOZ_TEST_SCOPE'] ?? 'shared';
if (!in_array($testScope, ['desktop', 'mobile'], true)) $testScope = 'shared';
// Isolate browser projects; both still exercise the real, unchanged login limits.
putenv('APP_STORAGE='.$root.'/backend/storage/'.$state['database'].'-limits-'.$testScope);
putenv('CAMERA_RECORDINGS_PATH='.$root.'/backend/storage/'.$state['database'].'-recordings');
$file=realpath($root.($path==='/'?'/index.php':$path));
if (!$file || !str_starts_with($file,$root.DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
if (pathinfo($file,PATHINFO_EXTENSION)==='php') { require $file; return; }
$types=['css'=>'text/css','js'=>'text/javascript','png'=>'image/png','jpg'=>'image/jpeg','svg'=>'image/svg+xml','webp'=>'image/webp'];
$ext=pathinfo($file,PATHINFO_EXTENSION);
if (!isset($types[$ext])) { http_response_code(403); exit; }
header('Content-Type: '.$types[$ext]); readfile($file);
