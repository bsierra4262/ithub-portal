<?php
declare(strict_types=1);

// ============================================================
//  Panel: las tarjetas de las aplicaciones a las que el usuario tiene
//  acceso, en una sola grilla con filtros por categoría y buscador. Cada
//  tarjeta lleva a /ir?app=<clave>, que valida el permiso otra vez, lo
//  anota y recién ahí abre la app.
// ============================================================

$apps = apps_del_usuario($yo);
$categorias = [];
foreach ($apps as $a) {
    $categorias[$a['categoria']] = ($categorias[$a['categoria']] ?? 0) + 1;
}

$resumen = null;
if (es_admin($yo)) {
    $resumen = [
        'usuarios' => (int)qv('SELECT COUNT(*) FROM usuarios WHERE activo = 1'),
        'apps'     => (int)qv("SELECT COUNT(*) FROM aplicaciones WHERE estado = 'activa'"),
        'sesiones' => (int)qv('SELECT COUNT(*) FROM sesiones WHERE cerrada_en IS NULL AND ultima_actividad >= ?',
                              [ahora(-(int)cfg('app.idle_ttl', 7200))]),
        'ingresos' => (int)qv("SELECT COUNT(*) FROM auditoria WHERE accion IN ('app_ingreso','login') AND creado_en >= ?",
                              [date('Y-m-d 00:00:00')]),
    ];
}

vista('panel', [
    'titulo'     => 'Inicio',
    'yo'         => $yo,
    'apps'       => $apps,
    'categorias' => $categorias,
    'total'      => count(array_filter($apps, static fn(array $a): bool => $a['mi_rol'] !== null)),
    'resumen'    => $resumen,
]);
