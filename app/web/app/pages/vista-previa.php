<?php
declare(strict_types=1);

// ============================================================
//  Vista previa: el panel tal como lo ve otra persona, para comprobar sus
//  permisos sin entrar con su cuenta. Sólo lectura: las tarjetas no abren la
//  aplicación y no se crea ninguna sesión a su nombre.
//  Admin y super admin (index.php y acá).
// ============================================================

if (!es_admin($yo)) {
    http_response_code(403);
    exit;
}

$persona = q1('SELECT * FROM usuarios WHERE id = ?', [(int)($_GET['usuario'] ?? 0)]);
if ($persona === null) {
    flash('error', 'El usuario no existe.');
    redirigir(url('permisos'));
}

$apps = apps_del_usuario($persona);
$categorias = [];
foreach ($apps as $a) {
    $categorias[$a['categoria']] = ($categorias[$a['categoria']] ?? 0) + 1;
}

vista('panel', [
    'titulo'     => 'Vista previa · ' . $persona['nombre'],
    'yo'         => $yo,
    'persona'    => $persona,
    'previa'     => true,
    'nav_activa' => 'permisos',
    'apps'       => $apps,
    'categorias' => $categorias,
    'total'      => count(array_filter($apps, static fn(array $a): bool => $a['mi_rol'] !== null)),
    'resumen'    => null,
]);
