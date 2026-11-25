<?php
declare(strict_types=1);

// ============================================================
//  Auditoría (admin): eventos más nuevos primero, 50 por página, con
//  filtros por persona, acción, aplicación y fecha.
// ============================================================

if (!es_admin($yo)) {
    http_response_code(403);
    exit;
}

$fUsuario = (int)($_GET['usuario'] ?? 0);
$fAccion = (string)($_GET['accion'] ?? '');
$fApp = (int)($_GET['app'] ?? 0);
$fDesde = (string)($_GET['desde'] ?? '');
$fHasta = (string)($_GET['hasta'] ?? '');
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 50;

$where = ['1 = 1'];
$p = [];
if ($fUsuario > 0) {
    $where[] = 'au.usuario_id = ?';
    $p[] = $fUsuario;
}
if (array_key_exists($fAccion, ACCIONES_AUDITORIA)) {
    $where[] = 'au.accion = ?';
    $p[] = $fAccion;
}
if ($fApp > 0) {
    $where[] = 'au.aplicacion_id = ?';
    $p[] = $fApp;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDesde)) {
    $where[] = 'au.creado_en >= ?';
    $p[] = $fDesde . ' 00:00:00';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fHasta)) {
    $where[] = 'au.creado_en <= ?';
    $p[] = $fHasta . ' 23:59:59';
}
$w = implode(' AND ', $where);

$total = (int)qv("SELECT COUNT(*) FROM auditoria au WHERE $w", $p);
$eventos = q("SELECT au.*, u.nombre AS usuario_nombre, u.email AS usuario_email, a.nombre AS app_nombre
              FROM auditoria au
              LEFT JOIN usuarios u ON u.id = au.usuario_id
              LEFT JOIN aplicaciones a ON a.id = au.aplicacion_id
              WHERE $w ORDER BY au.id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $p);

vista('auditoria', [
    'titulo'   => 'Auditoría',
    'yo'       => $yo,
    'eventos'  => $eventos,
    'total'    => $total,
    'pagina'   => $pagina,
    'paginas'  => max(1, (int)ceil($total / $porPagina)),
    'filtros'  => ['usuario' => $fUsuario, 'accion' => $fAccion, 'app' => $fApp, 'desde' => $fDesde, 'hasta' => $fHasta],
    'usuarios' => q('SELECT id, nombre FROM usuarios ORDER BY nombre'),
    'apps'     => q('SELECT id, nombre FROM aplicaciones ORDER BY nombre'),
]);
