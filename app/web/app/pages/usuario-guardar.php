<?php
declare(strict_types=1);

// ============================================================
//  Guarda un usuario y sus permisos (POST con CSRF).
//
//  Reglas que protegen el acceso:
//    · el email tiene que ser del dominio de Google Workspace;
//    · un admin no crea ni edita super admins, ni nombra a nadie super admin;
//    · nadie se desactiva ni se baja el rol a sí mismo (no se queda afuera);
//    · siempre queda al menos un super admin activo;
//    · desactivar a alguien cierra todas sus sesiones (y las de sus apps);
//    · quitarle una app cierra su sesión en esa app.
// ============================================================

if (!es_admin($yo)) {
    http_response_code(403);
    exit;
}
requerir_post();

$id = (int)($_POST['id'] ?? 0);
$actual = $id > 0 ? q1('SELECT * FROM usuarios WHERE id = ?', [$id]) : null;
if ($id > 0 && ($actual === null || !puede_editar_usuario($yo, $actual))) {
    flash('error', 'No podés editar ese usuario.');
    redirigir(url('usuarios'));
}

$dominio = (string)cfg('google.allowed_domain', '');
$email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
$nombre = trim((string)($_POST['nombre'] ?? ''));
$rol = (string)($_POST['rol'] ?? 'usuario');
$activo = !empty($_POST['activo']) ? 1 : 0;
$permisos = [];
foreach ((array)($_POST['permiso'] ?? []) as $appId => $r) {
    $permisos[(int)$appId] = (string)$r;
}

$errores = [];
if ($actual === null) {
    if ($dominio !== '' && !email_del_dominio($email, $dominio)) {
        $errores['email'] = "Tiene que ser una cuenta de Google Workspace @$dominio.";
    } elseif (usuario_por_email($email) !== null) {
        $errores['email'] = 'Ya hay un usuario con ese email.';
    }
} else {
    $email = $actual['email'];   // el email no se cambia: es la identidad de Google
}
if ($nombre === '' || mb_strlen($nombre) > 120) {
    $errores['nombre'] = 'El nombre es obligatorio (hasta 120 caracteres).';
}
if (!array_key_exists($rol, ROLES_PORTAL)) {
    $errores['rol'] = 'Rol inválido.';
} elseif ($rol === 'superadmin' && !es_superadmin($yo)) {
    $errores['rol'] = 'Sólo un super admin puede nombrar a otro super admin.';
}
if ($actual !== null && (int)$actual['id'] === $yo['id']) {
    if (!$activo) {
        $errores['activo'] = 'No podés desactivarte a vos mismo.';
    }
    if ($rol !== $actual['rol']) {
        $errores['rol'] = 'No podés cambiarte tu propio rol.';
    }
}
if ($actual !== null && $actual['rol'] === 'superadmin' && (int)$actual['activo'] === 1
    && ($rol !== 'superadmin' || !$activo) && superadmins_activos() <= 1) {
    $errores['rol'] = 'Es el único super admin activo: nombrá a otro antes.';
}

if ($errores) {
    $_SESSION['form_usuario'] = ['email' => $email, 'nombre' => $nombre, 'rol' => $rol, 'activo' => $activo, 'permisos' => $permisos];
    $_SESSION['form_errores'] = $errores;
    redirigir(url('usuario-form', $id ? ['id' => $id] : []));
}

$quitadas = tx(function () use ($actual, $email, $nombre, $rol, $activo, $permisos, $yo, &$id): array {
    if ($actual === null) {
        $id = crear_usuario($email, $nombre, $rol);
        if (!$activo) {
            qx('UPDATE usuarios SET activo = 0 WHERE id = ?', [$id]);
        }
        auditar($yo['id'], 'usuario_creado', null, $email, ['rol' => $rol]);
    } else {
        $cambios = [];
        foreach (['nombre' => $nombre, 'rol' => $rol, 'activo' => $activo] as $k => $v) {
            if ((string)$actual[$k] !== (string)$v) {
                $cambios[$k] = ['de' => $actual[$k], 'a' => $v];
            }
        }
        if ($cambios) {
            qx('UPDATE usuarios SET nombre = ?, rol = ?, activo = ?, actualizado_en = ? WHERE id = ?',
                [$nombre, $rol, $activo, ahora(), $id]);
            auditar($yo['id'], 'usuario_editado', null, $email, $cambios);
        }
    }
    return guardar_permisos($id, $email, $permisos, $yo['id']);
});

// Fuera de la transacción: los avisos a las apps son pedidos HTTP.
if ($actual !== null && (int)$actual['activo'] === 1 && !$activo) {
    $n = cerrar_sesiones_usuario($id, 'usuario_inactivo', $yo['id']);
    if ($n > 0) {
        flash('info', "Se cerraron $n sesiones abiertas de {$nombre}.");
    }
}
foreach ($quitadas as $appId) {
    oidc_revocar_app_usuario($id, $appId);
}

flash('ok', $actual === null ? "Usuario {$nombre} creado." : 'Cambios guardados.');
redirigir(url('usuarios'));
