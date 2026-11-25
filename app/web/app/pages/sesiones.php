<?php
declare(strict_types=1);

// ============================================================
//  Sesiones abiertas de todos (admin): quién, desde dónde, desde cuándo y
//  en qué apps entró. Desde acá se cierran (sesion-cerrar.php).
//  Una sesión vencida por inactividad todavía figura abierta en la base
//  hasta que alguien la usa; acá se muestra como «inactiva».
// ============================================================

if (!es_admin($yo)) {
    http_response_code(403);
    exit;
}

$filas = q('SELECT s.*, u.nombre, u.email, u.rol, u.avatar_url
            FROM sesiones s JOIN usuarios u ON u.id = s.usuario_id
            WHERE s.cerrada_en IS NULL
            ORDER BY s.ultima_actividad DESC');

$appsPorSesion = [];
foreach (q('SELECT sa.sesion_id, a.nombre, a.icono, a.color, sa.rol FROM sesion_apps sa
            JOIN aplicaciones a ON a.id = sa.aplicacion_id
            JOIN sesiones s ON s.id = sa.sesion_id
            WHERE s.cerrada_en IS NULL ORDER BY sa.ultimo_ingreso DESC') as $f) {
    $appsPorSesion[$f['sesion_id']][] = $f;
}

$limiteIdle = time() - (int)cfg('app.idle_ttl', 7200);
$limiteAbs = time() - (int)cfg('app.abs_ttl', 43200);
foreach ($filas as &$f) {
    $f['inactiva'] = strtotime($f['ultima_actividad']) < $limiteIdle || strtotime($f['creada_en']) < $limiteAbs;
    $f['apps'] = $appsPorSesion[$f['id']] ?? [];
    $f['editable'] = puede_editar_usuario($yo, $f) && $f['id'] !== $yo['sid'];
}
unset($f);

vista('sesiones', [
    'titulo'   => 'Sesiones',
    'yo'       => $yo,
    'sesiones' => $filas,
]);
