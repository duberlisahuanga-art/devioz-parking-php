<?php
declare(strict_types=1);
$state=json_decode(file_get_contents(__DIR__.'/../backend/storage/browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
foreach (['DB_DATABASE'=>'database','DB_HOST'=>'host','DB_USERNAME'=>'username','DB_PASSWORD'=>'password'] as $key=>$field) putenv($key.'='.$state[$field]);
require __DIR__.'/../backend/bootstrap.php';
restore_exception_handler();
$_SESSION['user_id']=1;
try {
    if (($argv[1]??'')==='confirm') PaymentController::confirm(1);
    elseif (($argv[1]??'')==='reject') PaymentController::reject(1);
    else throw new RuntimeException('Acción inválida');
    echo "ACCEPTED\n";
} catch (ValidationException $e) {
    echo "CONFLICT\n";
}
