<?php
declare(strict_types=1);

// ============================================================
//  Genera (o regenera) el secreto de OpenID Connect de una app (POST con
//  CSRF). El anterior deja de servir en el acto. El valor en claro pasa
//  por la sesión una sola vez para mostrarlo en el formulario de la app; en
//  la base queda sólo su SHA-256.
// ============================================================

if (!es_superadmin($yo)) {
    http_response_code(403);
    exit;
}
requerir_post();

$app = app_por_id((int)($_POST['id'] ?? 0));
if ($app === null) {
    redirigir(url('aplicaciones'));
}
$secreto = oidc_generar_secreto($app['id']);
auditar($yo['id'], 'secreto_generado', $app['id'], $app['clave'], ['reemplazo' => !empty($app['secreto_hash'])]);
$_SESSION['secreto_nuevo'] = ['app' => $app['id'], 'valor' => $secreto];
redirigir(url('aplicacion-form', ['id' => $app['id']]) . '#secreto');
