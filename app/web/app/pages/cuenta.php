<?php
declare(strict_types=1);

// ============================================================
//  Mi cuenta: datos (vienen de Google), apps y rol en cada una, y las
//  sesiones abiertas de la persona, con la opción de cerrarlas.
// ============================================================

$sesiones = q('SELECT s.*, (SELECT COUNT(*) FROM sesion_apps sa WHERE sa.sesion_id = s.id) AS apps
               FROM sesiones s WHERE s.usuario_id = ? AND s.cerrada_en IS NULL
               ORDER BY s.ultima_actividad DESC', [$yo['id']]);

vista('cuenta', [
    'titulo'   => 'Mi cuenta',
    'yo'       => $yo,
    'apps'     => array_values(array_filter(apps_del_usuario($yo), static fn(array $a): bool => $a['mi_rol'] !== null)),
    'sesiones' => $sesiones,
]);
