<?php
declare(strict_types=1);

// ============================================================
//  Guarda una aplicación (POST con CSRF). La clave no cambia después del
//  alta: es el client_id con el que la app se presenta ante ITHub.
// ============================================================

if (!es_superadmin($yo)) {
    http_response_code(403);
    exit;
}
requerir_post();

$id = (int)($_POST['id'] ?? 0);
$actual = $id > 0 ? app_por_id($id) : null;
if ($id > 0 && $actual === null) {
    redirigir(url('aplicaciones'));
}

[$d, $errores] = app_validar($_POST, $actual === null);
if ($actual === null && !isset($errores['clave']) && app_por_clave($d['clave']) !== null) {
    $errores['clave'] = 'Ya existe una aplicación con esa clave.';
}
if ($errores) {
    $_SESSION['form_app'] = $_POST;
    $_SESSION['form_errores'] = $errores;
    redirigir(url('aplicacion-form', $id ? ['id' => $id] : []));
}

$t = ahora();
$campos = ['nombre', 'descripcion', 'categoria', 'url', 'icono', 'color', 'estado', 'orden', 'roles',
           'rol_superadmin', 'redirect_uris', 'logout_uris', 'backchannel_uri'];
if ($actual === null) {
    qx('INSERT INTO aplicaciones (clave, ' . implode(', ', $campos) . ', creado_en, actualizado_en)
        VALUES (?' . str_repeat(', ?', count($campos)) . ', ?, ?)',
        array_merge([$d['clave']], array_map(static fn(string $c) => $d[$c], $campos), [$t, $t]));
    $id = qid();
    auditar($yo['id'], 'app_creada', $id, $d['clave']);
    flash('ok', "Aplicación {$d['nombre']} creada. Para el ingreso único, generá su secreto.");
} else {
    $set = implode(', ', array_map(static fn(string $c): string => "$c = ?", $campos));
    qx("UPDATE aplicaciones SET $set, actualizado_en = ? WHERE id = ?",
        array_merge(array_map(static fn(string $c) => $d[$c], $campos), [$t, $id]));
    $cambios = [];
    foreach ($campos as $c) {
        $antes = $c === 'roles' ? json_encode($actual['roles']) : (string)($actual[$c] ?? '');
        if ($antes !== (string)$d[$c]) {
            $cambios[] = $c;
        }
    }
    if ($cambios) {
        auditar($yo['id'], 'app_editada', $id, $actual['clave'], ['campos' => $cambios]);
    }
    flash('ok', 'Cambios guardados.');
}
redirigir(url('aplicacion-form', ['id' => $id]));
