<?php
declare(strict_types=1);

// ============================================================
//  SOLO DESARROLLO. index.php registra esta ruta únicamente con
//  app.env = 'dev' y desde 127.0.0.1: en producción no existe.
//  Abre la sesión de un usuario existente sin Google, para probar y sacar
//  capturas en la máquina local: /dev-login?email=...&volver=/panel
// ============================================================

if (cfg('app.env') !== 'dev' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
$u = usuario_por_email((string)($_GET['email'] ?? ''));
if ($u === null) {
    http_response_code(404);
    exit('No existe ese usuario en la base local.');
}
iniciar_sesion($u);
$_SESSION['volver'] = (string)($_GET['volver'] ?? '/panel');
redirigir(destino_post_login());
