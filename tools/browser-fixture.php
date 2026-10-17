<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$root=dirname(__DIR__);
if (!is_dir($root.'/backend/storage')) mkdir($root.'/backend/storage',0700,true);
$state=$root.'/backend/storage/browser-fixture.json';
$host=getenv('TEST_DB_HOST')?:'127.0.0.1';
$user=getenv('TEST_DB_USERNAME')?:'root';
$pass=getenv('TEST_DB_PASSWORD')?:'';
$pdo=new PDO('mysql:host='.$host.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if (($argv[1]??'')==='clean') {
    if (!is_file($state)) exit;
    $data=json_decode(file_get_contents($state),true,512,JSON_THROW_ON_ERROR);
    if (!preg_match('/^devioz_browser_[a-f0-9]{12}$/D',$data['database'])) throw new RuntimeException('Nombre de prueba inválido');
    $extra=$pdo->query('SELECT voucher_path FROM `'.$data['database'].'`.payments WHERE voucher_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($extra as $path) {
        if (preg_match('~^backend/uploads/comprobantes/(reserva_[0-9]+_[a-f0-9]{32}\.pdf)$~D',$path,$match)) {
            $file=$root.'/backend/uploads/comprobantes/'.$match[1];
            if (is_file($file)) unlink($file);
        }
    }
    $recordings=$root.'/backend/storage/'.$data['database'].'-recordings';
    if (is_dir($recordings)) {
        $storageRoot=realpath($root.'/backend/storage');
        $resolvedRecordings=realpath($recordings);
        if ($storageRoot === false || $resolvedRecordings === false
            || strcasecmp($resolvedRecordings, $storageRoot.DIRECTORY_SEPARATOR.basename($recordings)) !== 0) {
            throw new RuntimeException('Directorio de grabaciones de prueba inválido');
        }
        foreach (scandir($resolvedRecordings) as $entry) {
            if ($entry==='.' || $entry==='..') continue;
            if ($entry!=='.htaccess' && !preg_match('/^[a-f0-9]{32}\.(webm|mp4)$/D',$entry)) {
                throw new RuntimeException('Archivo inesperado en grabaciones de prueba');
            }
            if (!unlink($resolvedRecordings.DIRECTORY_SEPARATOR.$entry)) {
                throw new RuntimeException('No se pudo limpiar la grabación de prueba');
            }
        }
        rmdir($resolvedRecordings);
    }
    $pdo->exec('DROP DATABASE IF EXISTS `'.$data['database'].'`');
    foreach ($data['files'] as $file) {
        if (!preg_match('/^browser_[a-f0-9]{12}\.pdf$/D',$file)) throw new RuntimeException('Archivo de prueba inválido');
        $path=$root.'/backend/uploads/comprobantes/'.$file;
        if (is_file($path)) unlink($path);
    }
    unlink($state);
    exit("Fixture eliminado.\n");
}
if (is_file($state)) throw new RuntimeException('Existe un fixture. Limpiarlo antes de crear otro.');
$db='devioz_browser_'.bin2hex(random_bytes(6));
$pdo->exec(str_replace('devioz_parking',$db,file_get_contents($root.'/database/sql/schema.sql')));
foreach (['finance_expenses.sql','account_settings_notifications.sql','migrations/20261014_payment_integrity.sql'] as $path) {
    $pdo->exec(str_replace('devioz_parking',$db,file_get_contents($root.'/database/'.$path)));
}
$pdo->exec(file_get_contents($root . '/database/migrations/20261015_vehicle_catalog.sql'));
$pdo->exec(file_get_contents($root . '/database/migrations/20261015_reservation_renewals.sql'));
$pdo->exec(file_get_contents($root . '/database/migrations/20261015_password_recovery.sql'));
$pdo->exec(file_get_contents($root . '/database/migrations/20261016_camera_recordings.sql'));
require_once $root . '/backend/services/VehicleCatalogImporter.php';
VehicleCatalogImporter::import($pdo, $root . '/database/import/open-vehicle-db');
$pdo->exec("ALTER TABLE cameras ADD COLUMN channel_number TINYINT UNSIGNED NULL, ADD COLUMN location VARCHAR(150) NULL, ADD COLUMN rtsp_path VARCHAR(255) NULL, ADD COLUMN stream_protocol ENUM('hls','webrtc') DEFAULT 'hls', ADD COLUMN preferred_quality ENUM('sub','main') DEFAULT 'sub', ADD COLUMN brightness INT DEFAULT 100, ADD COLUMN contrast INT DEFAULT 100, ADD COLUMN saturation INT DEFAULT 100, ADD COLUMN osd_datetime TINYINT DEFAULT 1, ADD COLUMN osd_camera_name TINYINT DEFAULT 1");
$q=$pdo->prepare('INSERT INTO users(id,name,email,password,role,status) VALUES(?,?,?,?,?,?)');
foreach ([1=>'admin',2=>'driver',3=>'other'] as $id=>$role) $q->execute([$id,'Prueba '.$role,$role.'@browser.invalid',password_hash('Testing123!',PASSWORD_DEFAULT),$role==='admin'?'admin':'driver','active']);
$q->execute([4,'Prueba segundo administrador','recorder@browser.invalid',password_hash('Testing123!',PASSWORD_DEFAULT),'admin','active']);
$pdo->exec("INSERT INTO parkings(id,slug,name) VALUES(1,'devioz','Prueba'); INSERT INTO spaces(id,parking_id,code,status) VALUES(1,1,'P01','reserved'); INSERT INTO vehicles(id,user_id,plate) VALUES(1,2,'ABC-123'); INSERT INTO reservations(id,user_id,parking_id,space_id,vehicle_id,starts_on,ends_on,status,amount,payment_status) VALUES(1,2,1,1,1,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 MONTH),'confirmed',100,'pending')");
$pdo->exec("INSERT INTO spaces(id,parking_id,code,status) VALUES(2,1,'P02','reserved'),(3,1,'P03','reserved'); INSERT INTO reservations(id,user_id,parking_id,space_id,vehicle_id,starts_on,ends_on,status,amount,payment_status) VALUES(2,2,1,2,1,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 MONTH),'confirmed',100,'unpaid'),(3,2,1,3,1,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 MONTH),'confirmed',100,'unpaid')");
$filename='browser_'.bin2hex(random_bytes(6)).'.pdf';
file_put_contents($root.'/backend/uploads/comprobantes/'.$filename,"%PDF-1.4\n%%EOF\n");
$q=$pdo->prepare("INSERT INTO payments(id,reservation_id,amount,method,voucher_path,status) VALUES(1,1,100,'yape',?,'pending')");
$q->execute(['backend/uploads/comprobantes/'.$filename]);
$notice = $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,is_read,read_at) VALUES(?,'system_notices',?,?,?,IF(?=1,NOW(),NULL))");
foreach ([2, 3] as $noticeUser) {
    for ($noticeIndex = 1; $noticeIndex <= 60; $noticeIndex++) {
        $notice->execute([$noticeUser, 'Historial leído ' . $noticeIndex, 'Notificación sintética', 1, 1]);
    }
    $notice->execute([$noticeUser, 'Aviso pendiente', 'Debe conservarse al borrar el historial', 0, 0]);
}
file_put_contents($state,json_encode(['database'=>$db,'host'=>$host,'username'=>$user,'password'=>$pass,'files'=>[$filename]],JSON_THROW_ON_ERROR));
echo "Fixture sintético de navegador preparado.\n";
