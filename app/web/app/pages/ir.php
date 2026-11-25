<?php
declare(strict_types=1);

// ============================================================
//  Lanzador: /ir?app=<clave>. Lo usan las tarjetas del panel.
//
//  Vuelve a validar el permiso (la tarjeta pudo quedar abierta en otra
//  pestaña después de que se lo quitaran) y lleva a la URL de la app.
//  Si la app está conectada a ITHub, su pantalla de ingreso redirige sola a
//  /oauth/authorize y entra sin pedir nada; si todavía no, la app pide su
//  propio ingreso, como hasta ahora.
//
//  Para las apps sin ingreso único se anota acá la entrada en la auditoría;
//  las conectadas la anotan en /oauth/authorize (con el rol real).
// ============================================================

$app = app_por_clave((string)($_GET['app'] ?? ''));
$rol = $app ? rol_en_app($yo, $app) : null;

if ($app === null || $rol === null) {
    if ($app !== null) {
        auditar($yo['id'], 'app_denegada', $app['id'], $app['clave']);
    }
    http_response_code(403);
    vista('sin-acceso', ['titulo' => 'Sin acceso', 'yo' => $yo, 'app' => $app]);
    exit;
}

if (!$app['sso']) {
    auditar($yo['id'], 'app_ingreso', $app['id'], $app['clave'], ['rol' => $rol, 'via' => 'enlace']);
}
redirigir($app['url']);
