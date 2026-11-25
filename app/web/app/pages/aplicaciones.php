<?php
declare(strict_types=1);

// ============================================================
//  Catálogo de aplicaciones (super admin): tarjeta, estado, cuántas
//  personas tienen acceso y si está conectada al ingreso único.
// ============================================================

if (!es_superadmin($yo)) {
    http_response_code(403);
    exit;
}

$conAcceso = [];
foreach (q('SELECT aplicacion_id, COUNT(*) AS n FROM permisos GROUP BY aplicacion_id') as $f) {
    $conAcceso[(int)$f['aplicacion_id']] = (int)$f['n'];
}

vista('aplicaciones', [
    'titulo'     => 'Aplicaciones',
    'yo'         => $yo,
    'apps'       => apps_todas(),
    'con_acceso' => $conAcceso,
]);
