<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}
require_once __DIR__ . '/../backend/services/RequestSecurity.php';
require_once __DIR__ . '/../backend/services/CameraIceService.php';
$checks = 0;
function preparationCheck(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FALLO: ' . $name);
    }
    $checks++;
    echo 'OK: ' . $name . PHP_EOL;
}
$direct = ['REMOTE_ADDR' => '192.168.1.50', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_CF_CONNECTING_IP' => '203.0.113.8'];
preparationCheck(!RequestSecurity::isHttps($direct), 'cabecera HTTPS falsa se ignora por defecto');
preparationCheck(!RequestSecurity::isHttps($direct, '127.0.0.1'), 'equipo LAN no puede hacerse pasar por el proxy');
preparationCheck(RequestSecurity::isHttps([...$direct, 'HTTPS' => 'on']), 'HTTPS directo permanece valido');
$proxy = [...$direct, 'REMOTE_ADDR' => '127.0.0.1'];
preparationCheck(RequestSecurity::isHttps($proxy, '127.0.0.1'), 'HTTPS del proxy expresamente autorizado');
preparationCheck(!RequestSecurity::isHttps([...$proxy, 'HTTP_X_FORWARDED_PROTO' => 'https,http'], '127.0.0.1'), 'cabecera ambigua se rechaza');
preparationCheck(RequestSecurity::clientIp($direct, '127.0.0.1') === '192.168.1.50', 'IP falsa del cliente se ignora fuera del proxy');
preparationCheck(RequestSecurity::clientIp($proxy, '127.0.0.1') === '203.0.113.8', 'IP de cliente validada detras del proxy');
preparationCheck(RequestSecurity::clientIp([...$proxy, 'HTTP_CF_CONNECTING_IP' => 'invalid'], '127.0.0.1') === '127.0.0.1', 'IP mal formada usa el origen real');
preparationCheck(RequestSecurity::clientIp([]) === null, 'peticion sin IP no inventa un cliente');
$local = CameraIceService::build([], 7, 1700000000);
preparationCheck($local['iceServers'] === [] && $local['iceTransportPolicy'] === 'all', 'sin proveedor mantiene conexion LAN');
$configuration = ['turn_urls' => ['turns:relay.example.com:5349?transport=tcp'],
    'turn_shared_secret' => str_repeat('s', 32), 'ttl_seconds' => 600, 'transport_policy' => 'relay'];
$result = CameraIceService::build($configuration, 7, 1700000000);
preparationCheck($result['expiresAt'] === 1700000600, 'credenciales tienen caducidad de diez minutos');
preparationCheck($result['iceServers'][0]['username'] === '1700000600:devioz-7', 'credenciales asociadas al administrador');
preparationCheck(!str_contains(json_encode($result), str_repeat('s', 32)), 'el secreto maestro no llega al navegador');
preparationCheck($result['iceServers'][0]['credential'] !== CameraIceService::build($configuration, 8, 1700000000)['iceServers'][0]['credential'], 'otro administrador obtiene otra credencial');
foreach ([['turn_urls' => ['https://other.example']], ['turn_urls' => ['turn:user:password@host']],
    ['turn_urls' => ['turn:host:0']], ['turn_urls' => ['turn:host:70000']], ['stun_urls' => ['stun:host extra']],
    ['stun_urls' => 'bad'], ['transport_policy' => 'relay'], ['ttl_seconds' => 999999], ['transport_policy' => 'bad'],
    ['turn_urls' => ['turn:relay.example.com'], 'turn_shared_secret' => 'short']] as $invalid) {
    $rejected = false;
    try { CameraIceService::build($invalid, 7); } catch (InvalidArgumentException) { $rejected = true; }
    preparationCheck($rejected, 'configuracion ICE invalida rechazada');
}
echo $checks . " comprobaciones correctas.\n";
