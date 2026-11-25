<?php
declare(strict_types=1);

// ============================================================
//  Permisos: la matriz personas × aplicaciones en una sola pantalla.
//  Cada celda es el rol de la persona en esa app («—» = sin acceso: la
//  tarjeta no aparece en su panel y la app no la deja entrar). Se guarda con
//  permisos-guardar. Desde cada fila se puede ver el panel como esa persona.
//  Admin y super admin (index.php y acá).
// ============================================================

if (!es_admin($yo)) {
    http_response_code(403);
    exit;
}

$buscar = trim((string)($_GET['q'] ?? ''));
$inactivos = ($_GET['inactivos'] ?? '') === '1';

$where = ['1 = 1'];
$p = [];
if ($buscar !== '') {
    $where[] = '(nombre LIKE ? OR email LIKE ?)';
    $p[] = '%' . $buscar . '%';
    $p[] = '%' . $buscar . '%';
}
if (!$inactivos) {
    $where[] = 'activo = 1';
}
$usuarios = q('SELECT * FROM usuarios WHERE ' . implode(' AND ', $where) . ' ORDER BY nombre', $p);

$matriz = [];
foreach (q('SELECT usuario_id, aplicacion_id, rol FROM permisos') as $f) {
    $matriz[(int)$f['usuario_id']][(int)$f['aplicacion_id']] = $f['rol'];
}

$apps = array_values(array_filter(apps_todas(), static fn(array $a): bool => $a['estado'] !== 'proximamente'));
$conAcceso = [];
foreach ($apps as $a) {
    $conAcceso[$a['id']] = 0;
}
foreach (q('SELECT * FROM usuarios WHERE activo = 1') as $u) {
    foreach ($apps as $a) {
        if (rol_en_app($u, $a) !== null) {
            $conAcceso[$a['id']]++;
        }
    }
}

vista('permisos', [
    'titulo'     => 'Permisos',
    'yo'         => $yo,
    'usuarios'   => $usuarios,
    'apps'       => $apps,
    'matriz'     => $matriz,
    'con_acceso' => $conAcceso,
    'buscar'     => $buscar,
    'inactivos'  => $inactivos,
]);
