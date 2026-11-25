<?php
declare(strict_types=1);

// ============================================================
//  Router para el servidor de desarrollo de PHP (hace lo que en el hosting
//  hace el .htaccess). SÓLO para la máquina local:
//
//      php -S 127.0.0.1:8090 -t app/web test/router.php
//
//  Necesita app/web/config.local.php con app.env = 'dev' (ver
//  app/server-config/config.example.php; para SQLite: 'db' => ['driver' =>
//  'sqlite', 'path' => ...]).
// ============================================================

$ruta = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$archivo = $_SERVER['DOCUMENT_ROOT'] . $ruta;

// Los assets se sirven tal cual; el código de app/ nunca.
if ($ruta !== '/' && is_file($archivo) && !str_starts_with($ruta, '/app/')) {
    return false;
}
$_GET['r'] = ltrim($ruta, '/');
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
