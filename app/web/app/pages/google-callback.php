<?php
declare(strict_types=1);

// ============================================================
//  Vuelta de Google: valida, abre la sesión de ITHub y sigue.
//  Ver google.php para qué se valida y por qué.
// ============================================================

$state = (string)($_GET['state'] ?? '');
$esperado = (string)($_SESSION['g_state'] ?? '');
$nonce = (string)($_SESSION['g_nonce'] ?? '');
$verificador = (string)($_SESSION['g_verificador'] ?? '');
$desde = (int)($_SESSION['g_desde'] ?? 0);
unset($_SESSION['g_state'], $_SESSION['g_nonce'], $_SESSION['g_verificador'], $_SESSION['g_desde']);

if ($esperado === '' || !hash_equals($esperado, $state)) {
    redirigir(url('login', ['error' => 'vencido']));
}
if (isset($_GET['error']) || empty($_GET['code'])) {
    // La persona canceló en Google o Google devolvió un error.
    redirigir(url('login', ['error' => 'google']));
}
if ($desde < time() - 600) {
    redirigir(url('login', ['error' => 'vencido']));
}

try {
    $idToken = google_canjear_codigo((string)$_GET['code'], $verificador);
    $identidad = google_validar_identidad($idToken, $nonce);
} catch (RuntimeException $e) {
    error_log('[ithub] login con Google rechazado: ' . $e->getMessage());
    $fueraDeDominio = str_contains($e->getMessage(), 'fuera del dominio');
    auditar(null, 'login_rechazado', null, null, ['motivo' => $fueraDeDominio ? 'dominio' : 'google']);
    redirigir(url('login', ['error' => $fueraDeDominio ? 'dominio' : 'google']));
}

$motivo = null;
$usuario = google_resolver_usuario($identidad, $motivo);
if ($usuario === null) {
    auditar(null, 'login_rechazado', null, $identidad['email'], ['motivo' => $motivo]);
    redirigir(url('login', ['error' => $motivo]));
}

// Lo pendiente sobrevive a la regeneración del id de sesión.
iniciar_sesion($usuario);
redirigir(destino_post_login());
