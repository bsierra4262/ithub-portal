<?php
declare(strict_types=1);

// ============================================================
//  Arranque de la aplicación: lo incluye index.php en cada pedido.
//
//    1. constantes de rutas (APP, WEB, BASE_PATH);
//    2. configuración (cargar_config()), zona horaria y manejo de errores;
//    3. módulos de la aplicación;
//    4. cabeceras de seguridad;
//    5. sesión PHP — salvo en los endpoints de máquina a máquina de OpenID
//       Connect (token, userinfo, jwks, discovery), que no usan cookies.
//
//  Trampa: la cookie de sesión es SameSite=Lax a propósito. Cuando una app
//  manda al usuario a /oauth/authorize es una navegación GET de primer nivel
//  desde otro sitio: con Lax la cookie viaja y ITHub reconoce la sesión; con
//  Strict no viajaría y el usuario tendría que volver a entrar con Google en
//  cada app, que es justo lo que este sistema evita.
// ============================================================

define('APP', __DIR__);
define('WEB', dirname(__DIR__));

// La app vive en la raíz del subdominio; en desarrollo puede estar en una
// subcarpeta. Se deduce de dónde está index.php.
$__base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('BASE_PATH', $__base === '.' ? '' : $__base);

require APP . '/helpers.php';
require APP . '/db.php';
require APP . '/auditoria.php';
require APP . '/auth.php';
require APP . '/jwt.php';
require APP . '/http.php';
require APP . '/google.php';
require APP . '/aplicaciones.php';
require APP . '/oidc.php';
require APP . '/iconos.php';

/**
 * Busca la configuración, en este orden:
 *   1. la variable de entorno ITHUB_CONFIG (si alguien la define a propósito);
 *   2. /home/USUARIO/ithub_config/config.php, deducido de la ruta de la
 *      instalación (/home/USUARIO/domains/... → /home/USUARIO);
 *   3. app/server-config/config.php del repo (desarrollo);
 *   4. app/web/config.local.php (desarrollo).
 */
function cargar_config(): array
{
    $candidatos = [];
    if ($env = getenv('ITHUB_CONFIG')) {
        $candidatos[] = $env;
    }
    if (preg_match('#^(/home/[^/]+)/#', str_replace('\\', '/', WEB), $m)) {
        $candidatos[] = $m[1] . '/ithub_config/config.php';
    }
    $candidatos[] = dirname(WEB) . '/server-config/config.php';
    $candidatos[] = WEB . '/config.local.php';
    foreach ($candidatos as $c) {
        if (is_file($c)) {
            $cfg = require $c;
            if (is_array($cfg)) {
                return $cfg;
            }
        }
    }
    http_response_code(500);
    exit('ITHub no está configurado: falta config.php (ver app/server-config/config.example.php).');
}

$GLOBALS['CONFIG'] = $CONFIG = cargar_config();
date_default_timezone_set((string)cfg('app.timezone', 'America/Argentina/Buenos_Aires'));

if (cfg('app.env') === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Versión de los assets para el ?v= del CSS y el JS: la fecha del más nuevo.
define('ASSETS_V', (string)max(
    @filemtime(WEB . '/assets/css/app.css') ?: 0,
    @filemtime(WEB . '/assets/js/app.js') ?: 0
));

// ---- Cabeceras de seguridad ----
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://*.googleusercontent.com; "
        . "style-src 'self'; font-src 'self'; "
        . "script-src 'self'; frame-ancestors 'none'; base-uri 'self'");
    if (es_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/** Abre la sesión PHP con cookies seguras. */
function abrir_sesion_php(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }
    session_name('ithub');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => es_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)cfg('app.abs_ttl', 43200));
    session_start();
}
