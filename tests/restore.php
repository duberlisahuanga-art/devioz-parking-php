<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$state=json_decode(file_get_contents($root.'/backend/storage/browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/^devioz_browser_[a-f0-9]{12}$/D',$state['database'])) throw new RuntimeException('Base de prueba inválida');
$target='devioz_restore_'.bin2hex(random_bytes(6));
$backup=$root.'/backend/storage/'.$target.'.sql';
$binary=getenv('MYSQLDUMP_BIN')?: (is_file('C:/xampp/mysql/bin/mysqldump.exe')?'C:/xampp/mysql/bin/mysqldump.exe':'mysqldump');
$environment=array_merge(getenv(),['MYSQL_PWD'=>$state['password']]);
$process=proc_open([$binary,'--host='.$state['host'],'--user='.$state['username'],'--single-transaction','--result-file='.$backup,$state['database']], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$environment);
if (!is_resource($process)) throw new RuntimeException('No se inició mysqldump');
fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
if (proc_close($process)!==0) throw new RuntimeException('Falló el respaldo sintético: '.$error);
$pdo=new PDO('mysql:host='.$state['host'].';charset=utf8mb4',$state['username'],$state['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
try {
    $pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `'.$target.'`');
    $pdo->exec(file_get_contents($backup));
    foreach (['users','reservations','payments','audit_logs'] as $table) {
        $actual=$pdo->query('SELECT COUNT(*) FROM `'.$target.'`.`'.$table.'`')->fetchColumn();
        $expected=$pdo->query('SELECT COUNT(*) FROM `'.$state['database'].'`.`'.$table.'`')->fetchColumn();
        if ($actual!==$expected) throw new RuntimeException('Restauración incompleta: '.$table);
    }
    echo "OK: respaldo sintético restaurado en otra base y conteos verificados.\n";
} finally {
    if (!preg_match('/^devioz_restore_[a-f0-9]{12}$/D',$target)) throw new RuntimeException('Nombre de limpieza inválido');
    $pdo->exec('DROP DATABASE IF EXISTS `'.$target.'`');
    if (is_file($backup)) unlink($backup);
}
