<?php
declare(strict_types=1);

// ============================================================
//  Front controller: la única puerta de entrada HTTP de ITHub.
//
//  El `.htaccess` manda acá todo pedido que no sea un archivo real, con la
//  ruta en `?r=` (ej. /usuario-form?id=3 → index.php?r=usuario-form&id=3).
//  Este archivo:
//    1. arranca la aplicación (bootstrap.php);
//    2. busca la ruta en `$rutas` (lo que no está ahí es 404);
//    3. abre la sesión PHP, salvo en las rutas de máquina a máquina (api);
//    4. exige sesión si la ruta no es pública (si no, al login);
//    5. exige el rol del portal que pida la ruta;
//    6. incluye el controlador `app/pages/<ruta>.php`.
//
//  Trampas:
//    · El controlador se incluye por el nombre de la ruta, así que sólo se
//      llega a un archivo de `pages/` que figure como clave de `$rutas`.
//    · Las pantallas de administración se protegen acá ('rol') y otra vez
//      dentro de cada controlador. Una ruta nueva de administración tiene
//      que marcarse en los dos lados.
//    · 'dev-login' existe sólo con app.env = 'dev' y desde 127.0.0.1: sirve
//      para sacar capturas en una máquina de desarrollo sin Google.
// ============================================================

require __DIR__ . '/app/bootstrap.php';

$ruta = trim((string)($_GET['r'] ?? ''), '/');
if ($ruta === '') {
    $ruta = 'home';
}

/**
 * Rutas registradas.
 *   publica → accesible sin sesión
 *   rol     → rol mínimo del portal: 'admin' (admin y super admin) o 'superadmin'
 *   api     → máquina a máquina: sin sesión PHP ni cookies (OpenID Connect)
 *   archivo → controlador, si no se llama igual que la ruta
 */
$rutas = [
    // --- Acceso ---
    'home'               => ['publica' => false],
    'login'              => ['publica' => true],
    'google-login'       => ['publica' => true],
    'google-callback'    => ['publica' => true],
    'salir'              => ['publica' => false],

    // --- Panel y cuenta ---
    'panel'              => ['publica' => false],
    'ir'                 => ['publica' => false],
    'cuenta'             => ['publica' => false],
    'sesion-cerrar'      => ['publica' => false],

    // --- Administración ---
    'usuarios'           => ['publica' => false, 'rol' => 'admin'],
    'usuario-form'       => ['publica' => false, 'rol' => 'admin'],
    'usuario-guardar'    => ['publica' => false, 'rol' => 'admin'],
    'permisos'           => ['publica' => false, 'rol' => 'admin'],
    'permisos-guardar'   => ['publica' => false, 'rol' => 'admin'],
    'vista-previa'       => ['publica' => false, 'rol' => 'admin'],
    'sesiones'           => ['publica' => false, 'rol' => 'admin'],
    'auditoria'          => ['publica' => false, 'rol' => 'admin'],
    'aplicaciones'       => ['publica' => false, 'rol' => 'superadmin'],
    'aplicacion-form'    => ['publica' => false, 'rol' => 'superadmin'],
    'aplicacion-guardar' => ['publica' => false, 'rol' => 'superadmin'],
    'aplicacion-secreto' => ['publica' => false, 'rol' => 'superadmin'],

    // --- OpenID Connect ---
    'oauth/authorize'    => ['publica' => true],
    'oauth/logout'       => ['publica' => true],
    'oauth/token'        => ['publica' => true, 'api' => true],
    'oauth/userinfo'     => ['publica' => true, 'api' => true],
    'oauth/jwks'         => ['publica' => true, 'api' => true],
    '.well-known/openid-configuration' => ['publica' => true, 'api' => true, 'archivo' => 'well-known/openid-configuration'],
];

if (cfg('app.env') === 'dev' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    $rutas['dev-login'] = ['publica' => true];
}

$def = $rutas[$ruta] ?? null;
if ($def === null) {
    abrir_sesion_php();
    http_response_code(404);
    vista('error', ['titulo' => 'Página no encontrada', 'mensaje' => 'La dirección no existe o se movió.']);
    exit;
}

if (empty($def['api'])) {
    abrir_sesion_php();
    header('Cache-Control: no-store');
}

$yo = empty($def['api']) ? usuario_actual() : null;

if (!$def['publica'] && $yo === null) {
    // Se recuerda adónde iba (sólo rutas internas) para volver después del login.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $ruta !== 'home') {
        $_SESSION['volver'] = $_SERVER['REQUEST_URI'] ?? '';
    }
    redirigir(url('login'));
}

$rolPedido = $def['rol'] ?? null;
if (($rolPedido === 'admin' && !es_admin($yo)) || ($rolPedido === 'superadmin' && !es_superadmin($yo))) {
    http_response_code(403);
    vista('error', ['titulo' => 'Sin permiso', 'mensaje' => 'Esta sección es sólo para administradores de ITHub.', 'yo' => $yo]);
    exit;
}

require APP . '/pages/' . ($def['archivo'] ?? $ruta) . '.php';
