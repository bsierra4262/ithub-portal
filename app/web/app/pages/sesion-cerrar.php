<?php
declare(strict_types=1);

// ============================================================
//  Cierra sesiones (POST con CSRF).
//
//    sid=<id>          una sesión: propia, o de otro si es admin y puede
//                      editar a esa persona
//    todas=1           todas las propias menos la actual
//    usuario=<id>      todas las de otra persona (admin)
//
//  Cada cierre se avisa a las apps a las que se entró con esa sesión.
// ============================================================

requerir_post();
$volver = ($_POST['volver'] ?? '') === 'sesiones' && es_admin($yo) ? url('sesiones') : url('cuenta');

if (!empty($_POST['todas'])) {
    $n = cerrar_sesiones_usuario($yo['id'], 'todas', $yo['id'], $yo['sid']);
    auditar($yo['id'], 'sesiones_cerradas', null, $yo['email'], ['cantidad' => $n]);
    flash('ok', $n === 1 ? 'Se cerró 1 sesión.' : "Se cerraron $n sesiones.");
    redirigir($volver);
}

if (!empty($_POST['usuario'])) {
    $obj = q1('SELECT * FROM usuarios WHERE id = ?', [(int)$_POST['usuario']]);
    if ($obj === null || !puede_editar_usuario($yo, $obj)) {
        flash('error', 'No podés cerrar las sesiones de esa persona.');
        redirigir($volver);
    }
    $n = cerrar_sesiones_usuario((int)$obj['id'], 'revocada', $yo['id'], $yo['sid']);
    auditar($yo['id'], 'sesiones_cerradas', null, $obj['email'], ['cantidad' => $n]);
    flash('ok', "Se cerraron $n sesiones de {$obj['nombre']}.");
    redirigir($volver);
}

$s = q1('SELECT s.*, u.email, u.rol, u.nombre FROM sesiones s JOIN usuarios u ON u.id = s.usuario_id WHERE s.id = ?',
    [(string)($_POST['sid'] ?? '')]);
$propia = $s !== null && (int)$s['usuario_id'] === $yo['id'];
if ($s === null || (!$propia && !puede_editar_usuario($yo, $s))) {
    flash('error', 'No se encontró la sesión.');
    redirigir($volver);
}
if ($s['id'] === $yo['sid']) {
    flash('error', 'Esa es la sesión que estás usando. Para cerrarla usá «Cerrar sesión».');
    redirigir($volver);
}
cerrar_sesion($s['id'], $propia ? 'salida' : 'revocada', $yo['id']);
auditar($yo['id'], 'sesion_cerrada', null, $s['email']);
flash('ok', 'Sesión cerrada' . ($propia ? '.' : ' de ' . $s['nombre'] . '.'));
redirigir($volver);
