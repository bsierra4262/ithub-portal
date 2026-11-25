<?php
declare(strict_types=1);

// ============================================================
//  Alta y edición de un usuario: datos, rol del portal, estado y la matriz
//  de permisos (un rol por aplicación, o «Sin acceso»).
// ============================================================

if (!es_admin($yo)) {
    http_response_code(403);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$usuario = null;
if ($id > 0) {
    $usuario = q1('SELECT * FROM usuarios WHERE id = ?', [$id]);
    if ($usuario === null) {
        flash('error', 'El usuario no existe.');
        redirigir(url('usuarios'));
    }
    if (!puede_editar_usuario($yo, $usuario)) {
        flash('error', 'Sólo un super admin puede editar a otro super admin.');
        redirigir(url('usuarios'));
    }
}

// Lo que quedó cargado si el guardado anterior falló.
$previo = $_SESSION['form_usuario'] ?? null;
$errores = $_SESSION['form_errores'] ?? [];
unset($_SESSION['form_usuario'], $_SESSION['form_errores']);

$sesionesAbiertas = $usuario
    ? (int)qv('SELECT COUNT(*) FROM sesiones WHERE usuario_id = ? AND cerrada_en IS NULL', [$usuario['id']])
    : 0;

vista('usuario-form', [
    'titulo'    => $usuario ? 'Editar usuario' : 'Nuevo usuario',
    'yo'        => $yo,
    'usuario'   => $usuario,
    'apps'      => array_values(array_filter(apps_todas(), static fn(array $a): bool => $a['estado'] !== 'proximamente')),
    'permisos'  => $previo['permisos'] ?? ($usuario ? permisos_de((int)$usuario['id']) : []),
    'previo'    => $previo,
    'errores'   => $errores,
    'dominio'   => (string)cfg('google.allowed_domain', ''),
    'sesiones_abiertas' => $sesionesAbiertas,
]);
