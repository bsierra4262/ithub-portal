<?php
declare(strict_types=1);

// ============================================================
//  Guarda la matriz de Permisos. Llega permiso[usuario_id][aplicacion_id] =
//  rol ('' = sin acceso) para las filas que se mostraron. Sólo se tocan las
//  personas que el actor puede editar (un admin no toca a un super admin) y
//  cada cambio queda en la auditoría. A quien se le quita una app se le cierra
//  la sesión en esa app.
// ============================================================

if (!es_admin($yo)) {
    http_response_code(403);
    exit;
}
requerir_post();

$pedido = $_POST['permiso'] ?? [];
if (!is_array($pedido)) {
    $pedido = [];
}

$cambios = 0;
$personas = 0;
$quitar = [];
foreach ($pedido as $uid => $porApp) {
    $u = q1('SELECT * FROM usuarios WHERE id = ?', [(int)$uid]);
    if ($u === null || !is_array($porApp) || !puede_editar_usuario($yo, $u)) {
        continue;
    }
    $limpio = [];
    foreach ($porApp as $aid => $rol) {
        $limpio[(int)$aid] = is_string($rol) ? trim($rol) : '';
    }
    $antes = permisos_de((int)$u['id']);
    $quitadas = tx(fn(): array => guardar_permisos((int)$u['id'], $u['email'], $limpio, $yo['id']));
    $despues = permisos_de((int)$u['id']);
    $n = count(array_diff_assoc($despues, $antes)) + count(array_diff_key($antes, $despues));
    if ($n > 0) {
        $cambios += $n;
        $personas++;
    }
    foreach ($quitadas as $aid) {
        $quitar[] = [(int)$u['id'], $aid];
    }
}

// Fuera de las transacciones: los avisos a las apps son pedidos HTTP.
foreach ($quitar as [$uid, $aid]) {
    oidc_revocar_app_usuario($uid, $aid);
}

if ($cambios === 0) {
    flash('info', 'No había cambios para guardar.');
} else {
    flash('ok', "Permisos guardados: $cambios " . ($cambios === 1 ? 'cambio' : 'cambios') . " en $personas "
        . ($personas === 1 ? 'persona' : 'personas') . '.');
}

$volver = [];
foreach (['q', 'inactivos'] as $k) {
    if (isset($_POST['_' . $k]) && $_POST['_' . $k] !== '') {
        $volver[$k] = (string)$_POST['_' . $k];
    }
}
redirigir(url('permisos', $volver));
