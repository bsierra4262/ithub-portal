<?php
declare(strict_types=1);

// ============================================================
//  /oauth/authorize — una app pide saber quién es el usuario.
//
//  Con sesión de ITHub y permiso: código y vuelta inmediata (la persona no
//  ve nada). Sin sesión: login con Google y se retoma solo
//  (destino_post_login). Sin permiso: pantalla «sin acceso» en ITHub, salvo
//  prompt=none, que vuelve a la app con el error. Ver oidc.php.
// ============================================================

try {
    $pedido = oidc_validar_autorizacion($_GET);
} catch (OidcError $e) {
    if ($e->redirigible && $e->pedido !== null) {
        redirigir(oidc_url_error($e->pedido, $e->codigo, $e->getMessage()));
    }
    http_response_code(400);
    vista('error', [
        'titulo'  => 'No se pudo ingresar',
        'mensaje' => $e->getMessage() . ' Avisale al administrador de la aplicación.',
        'detalle' => $e->codigo,
        'yo'      => $yo,
    ]);
    exit;
}

$app = $pedido['app'];

// prompt=login: la app pide que la persona vuelva a entrar con Google.
if ($yo !== null && str_contains($pedido['prompt'], 'login')) {
    cerrar_sesion($yo['sid'], 'salida', $yo['id']);
    salir_local();
    abrir_sesion_php();
    $yo = null;
    $q = $_GET;
    unset($q['r']);
    $q['prompt'] = trim(str_replace('login', '', $pedido['prompt']));
    $_SESSION['oidc_pendiente'] = http_build_query($q);
    $_SESSION['oidc_pendiente_app'] = $app['nombre'];
    redirigir(url('login'));
}

if ($yo === null) {
    if (str_contains($pedido['prompt'], 'none')) {
        redirigir(oidc_url_error($pedido, 'login_required', 'No hay sesión de ITHub.'));
    }
    $q = $_GET;
    unset($q['r']);
    $_SESSION['oidc_pendiente'] = http_build_query($q);
    $_SESSION['oidc_pendiente_app'] = $app['nombre'];
    redirigir(url('login'));
}

$rol = rol_en_app($yo, $app);
if ($rol === null) {
    auditar($yo['id'], 'app_denegada', $app['id'], $app['clave']);
    if (str_contains($pedido['prompt'], 'none')) {
        redirigir(oidc_url_error($pedido, 'access_denied', 'Sin acceso a esta aplicación.'));
    }
    http_response_code(403);
    vista('sin-acceso', ['titulo' => 'Sin acceso', 'yo' => $yo, 'app' => $app]);
    exit;
}

redirigir(oidc_emitir_codigo($pedido, $yo, $yo['sid'], $rol));
