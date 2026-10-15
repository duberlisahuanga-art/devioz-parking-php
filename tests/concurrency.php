<?php
declare(strict_types=1);
$state=json_decode(file_get_contents(__DIR__.'/../backend/storage/browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
if (!preg_match('/^devioz_browser_[a-f0-9]{12}$/D',$state['database'])) throw new RuntimeException('Base de prueba inválida');
$pdo=new PDO('mysql:host='.$state['host'].';dbname='.$state['database'].';charset=utf8mb4',$state['username'],$state['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function race(array $actions): array {
    $jobs=[];
    foreach ($actions as $action) {
        $process=proc_open([PHP_BINARY,__DIR__.'/concurrency-worker.php',$action],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
        if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar el proceso');
        fclose($pipes[0]); $jobs[]=[$process,$pipes];
    }
    $results=[];
    foreach ($jobs as [$process,$pipes]) {
        $output=trim(stream_get_contents($pipes[1])); $error=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        if (proc_close($process)!==0) throw new RuntimeException('Falló un proceso concurrente: '.$error);
        $results[]=$output;
    }
    return $results;
}
$results=race(['confirm','confirm']);
if ($results!==['ACCEPTED','ACCEPTED']) throw new RuntimeException('Reintento concurrente no idempotente');
$count=$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type='Payment' AND action='Aprobado'")->fetchColumn();
if ((int)$count!==1) throw new RuntimeException('Auditoría de aprobación duplicada');
echo "OK: dos aprobaciones concurrentes generan una sola decisión.\n";
// Sólo fixtures: restaurar a pendiente para enfrentar decisiones contrarias.
$pdo->exec("UPDATE payments SET status='pending' WHERE id=1; UPDATE reservations SET payment_status='pending' WHERE id=1; DELETE FROM audit_logs WHERE entity_type='Payment'; DELETE FROM notifications");
$results=race(['confirm','reject']);
sort($results);
if ($results!==['ACCEPTED','CONFLICT']) throw new RuntimeException('Se aceptaron decisiones contradictorias');
$row=$pdo->query('SELECT p.status,r.payment_status FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE p.id=1')->fetch(PDO::FETCH_ASSOC);
if (($row['status']==='paid' && $row['payment_status']!=='paid') || ($row['status']==='rejected' && $row['payment_status']!=='unpaid')) throw new RuntimeException('Estados financieros inconsistentes');
echo "OK: aprobar y rechazar concurrentemente no produce estados contradictorios.\n";
