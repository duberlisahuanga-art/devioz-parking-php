<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
$secret = getenv('DEVIOZ_SMTP_SETUP_SECRET');
if ($secret === false || trim($secret) === '') throw new RuntimeException('No se recibió contraseña de aplicación.');
$target = __DIR__ . '/../backend/config/local.php';
$values = require $target;
$cleanSecret = preg_replace('/\s+/', '', $secret);
if (($values['SMTP_HOST'] ?? '') === 'smtp.gmail.com' && strlen($cleanSecret) !== 16) {
    fwrite(STDERR, "La clave debe contener 16 caracteres sin espacios. No se modificó la configuración.\n");
    exit(1);
}
$values['SMTP_PASSWORD'] = $cleanSecret;
$temporary = tempnam(dirname($target),'smtp-');
if ($temporary === false || file_put_contents($temporary,"<?php\nreturn " . var_export($values,true) . ";\n",LOCK_EX) === false
    || !rename($temporary,$target)) throw new RuntimeException('No se pudo guardar configuración privada.');
echo "Contraseña SMTP guardada en configuración privada; no se imprimió.\n";
