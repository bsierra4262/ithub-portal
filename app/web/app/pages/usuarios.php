<?php
declare(strict_types=1);

// ============================================================
//  Usuarios: listado con búsqueda y filtros (rol del portal, estado).
//  Admin y super admin (index.php y acá).
// ============================================================

if (!es_admin($yo)) {
    http_response_code(403);
    exit;
}

$buscar = trim((string)($_GET['q'] ?? ''));
$rol = (string)($_GET['rol'] ?? '');
$estado = (string)($_GET['estado'] ?? '');

$where = ['1 = 1'];
$p = [];
if ($buscar !== '') {
    $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)';
    $p[] = '%' . $buscar . '%';
    $p[] = '%' . $buscar . '%';
}
if (array_key_exists($rol, ROLES_PORTAL)) {
    $where[] = 'u.rol = ?';
    $p[] = $rol;
}
if ($estado === 'activos' || $estado === 'inactivos') {
    $where[] = 'u.activo = ?';
    $p[] = $estado === 'activos' ? 1 : 0;
}

$usuarios = q('SELECT u.*,
                  (SELECT COUNT(*) FROM permisos pe WHERE pe.usuario_id = u.id) AS apps,
                  (SELECT COUNT(*) FROM sesiones s WHERE s.usuario_id = u.id AND s.cerrada_en IS NULL) AS sesiones
               FROM usuarios u WHERE ' . implode(' AND ', $where) . '
               ORDER BY u.activo DESC, u.nombre', $p);

vista('usuarios', [
    'titulo'   => 'Usuarios',
    'yo'       => $yo,
    'usuarios' => $usuarios,
    'buscar'   => $buscar,
    'rol'      => $rol,
    'estado'   => $estado,
    'total_apps' => (int)qv("SELECT COUNT(*) FROM aplicaciones WHERE estado <> 'proximamente'"),
]);
