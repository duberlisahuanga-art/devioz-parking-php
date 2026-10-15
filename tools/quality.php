<?php

declare(strict_types=1);

// Baseline reproducible: no arranca servicios ni modifica la base de datos.
$root = dirname(__DIR__);
$failures = 0;
$phpFiles = [];

foreach (['backend', 'frontend', 'tests', 'tools'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
}
$phpFiles[] = $root . '/index.php';
sort($phpFiles);

function runCheck(array $command, string $workingDirectory): array
{
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory
    );
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar la comprobacion.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output . $errors];
}

foreach ($phpFiles as $file) {
    [$status, $output] = runCheck([PHP_BINARY, '-l', $file], $root);
    if ($status !== 0) {
        $failures++;
        fwrite(STDERR, $output);
    }
}
echo 'PHP lint: ' . count($phpFiles) . ' archivos revisados.' . PHP_EOL;

$skillFiles = glob($root . '/.agents/skills/*/SKILL.md') ?: [];
$names = [];
if ($skillFiles === []) {
    $failures++;
    fwrite(STDERR, 'No se encontraron skills locales.' . PHP_EOL);
}
foreach ($skillFiles as $file) {
    $content = file_get_contents($file);
    $valid = is_string($content)
        && preg_match('/\A---\R(.*?)\R---\R/s', $content, $frontmatter) === 1;
    if ($valid) {
        $valid = preg_match('/^name: ([a-z0-9-]{1,63})\s*$/m', $frontmatter[1], $name) === 1
            && preg_match('/^description: \S[^\r\n]+$/m', $frontmatter[1]) === 1
            && $name[1] === basename(dirname($file))
            && !isset($names[$name[1]]);
    }
    if (!$valid) {
        $failures++;
        fwrite(STDERR, 'Manifest de skill invalido: ' . basename(dirname($file)) . PHP_EOL);
    } else {
        $names[$name[1]] = true;
    }
}
echo 'Skills: ' . count($skillFiles) . ' manifests revisados (validacion basica).' . PHP_EOL;

[$status, $output] = runCheck([PHP_BINARY, $root . '/tests/reservation_entry_policy.php'], $root);
echo $output;
if ($status !== 0) {
    $failures++;
}

if (!is_file($root . '/vendor/autoload.php')) {
    $failures++;
    fwrite(STDERR, 'Faltan dependencias. Ejecuta composer install.' . PHP_EOL);
} else {
    foreach ([['bin/phpunit'], ['bin/phpstan', 'analyse', '--memory-limit=512M', '--no-progress'], ['bin/phpcs']] as $check) {
        $binary = array_shift($check);
        [$status, $output] = runCheck(array_merge([PHP_BINARY, $root . '/vendor/' . $binary], $check), $root);
        echo $output;
        if ($status !== 0) {
            $failures++;
        }
    }
}

echo $failures === 0
    ? 'BASELINE OK. No sustituye pruebas de integracion, navegador ni auditoria de seguridad.' . PHP_EOL
    : 'BASELINE FALLIDO: ' . $failures . ' comprobaciones fallaron.' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
