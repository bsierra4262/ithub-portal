<?php
declare(strict_types=1);

// ============================================================
//  Alta y edición de una aplicación: tarjeta del panel, roles y datos de
//  OpenID Connect. El secreto se genera aparte (aplicacion-secreto.php) y
//  se muestra una sola vez, acá mismo, después de generarlo.
// ============================================================

if (!es_superadmin($yo)) {
    http_response_code(403);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$app = $id > 0 ? app_por_id($id) : null;
if ($id > 0 && $app === null) {
    flash('error', 'La aplicación no existe.');
    redirigir(url('aplicaciones'));
}

$previo = $_SESSION['form_app'] ?? null;
$errores = $_SESSION['form_errores'] ?? [];
$secreto = $_SESSION['secreto_nuevo'] ?? null;
unset($_SESSION['form_app'], $_SESSION['form_errores'], $_SESSION['secreto_nuevo']);
// El secreto recién generado sólo se muestra en la pantalla de su app.
if ($secreto !== null && ($app === null || $secreto['app'] !== $app['id'])) {
    $secreto = null;
}

$v = $previo ?? ($app ? array_merge($app, ['roles' => implode(', ', $app['roles'])]) : [
    'clave' => '', 'nombre' => '', 'descripcion' => '', 'categoria' => '', 'url' => '', 'icono' => 'app',
    'color' => 'violeta', 'estado' => 'activa', 'orden' => 100, 'roles' => 'admin, usuario', 'rol_superadmin' => '',
    'redirect_uris' => '', 'logout_uris' => '', 'backchannel_uri' => '',
]);

vista('aplicacion-form', [
    'titulo'   => $app ? $app['nombre'] : 'Nueva aplicación',
    'yo'       => $yo,
    'app'      => $app,
    'v'        => $v,
    'errores'  => $errores,
    'secreto'  => $secreto['valor'] ?? null,
    'categorias' => array_column(q('SELECT DISTINCT categoria FROM aplicaciones ORDER BY categoria'), 'categoria'),
]);
