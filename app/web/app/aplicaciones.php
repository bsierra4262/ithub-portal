<?php
declare(strict_types=1);

// ============================================================
//  Catálogo de aplicaciones y permisos por aplicación.
//
//  Cada aplicación es, a la vez:
//    · una tarjeta del panel (nombre, descripción, ícono, color, categoría);
//    · un cliente de OpenID Connect (clave = client_id, secreto, URIs).
//
//  Permisos: una fila de `permisos` por usuario y app, con el rol dentro de
//  esa app. Los roles válidos los define cada app (columna `roles`, JSON) y
//  se guardan con los mismos nombres que usa la app (admin, operador,
//  comercial...), así la app los aplica sin traducir nada.
//
//  Regla de acceso (rol_en_app()):
//    · app 'proximamente'          → nadie entra;
//    · app 'mantenimiento'         → sólo super admin;
//    · super admin                 → su permiso explícito o, si no tiene,
//                                    el rol_superadmin de la app (o el primero);
//    · resto                       → su permiso, si el rol sigue siendo válido.
// ============================================================

const ESTADOS_APP = [
    'activa'        => 'Activa',
    'mantenimiento' => 'En mantenimiento',
    'proximamente'  => 'Próximamente',
];

/**
 * Colores de tarjeta (paleta de la casa). Los tonos están en app.css como
 * clases .c-<nombre>: la política de seguridad no deja estilos en línea.
 */
const COLORES_APP = [
    'violeta' => 'Violeta',
    'uva'     => 'Uva',
    'lila'    => 'Lila',
    'lavanda' => 'Lavanda',
    'crema'   => 'Crema',
    'tinta'   => 'Tinta',
];

/** Normaliza una fila de `aplicaciones`: roles como array, URIs como listas. */
function app_normalizar(array $a): array
{
    $a['id'] = (int)$a['id'];
    $a['orden'] = (int)$a['orden'];
    $roles = json_decode((string)$a['roles'], true);
    $a['roles'] = is_array($roles) ? array_values(array_filter($roles, 'is_string')) : [];
    $a['redirect_uris_lista'] = app_lineas($a['redirect_uris'] ?? '');
    $a['logout_uris_lista'] = app_lineas($a['logout_uris'] ?? '');
    $a['sso'] = !empty($a['secreto_hash']) && $a['redirect_uris_lista'] !== [];
    return $a;
}

/** Texto con una URI por línea → lista limpia. */
function app_lineas(?string $txt): array
{
    $l = array_map('trim', preg_split('/\r?\n/', (string)$txt) ?: []);
    return array_values(array_filter($l, static fn(string $x): bool => $x !== ''));
}

/** @return array<int,array> todas, en el orden del panel */
function apps_todas(): array
{
    return array_map('app_normalizar', q('SELECT * FROM aplicaciones ORDER BY orden, nombre'));
}

function app_por_id(int $id): ?array
{
    $a = q1('SELECT * FROM aplicaciones WHERE id = ?', [$id]);
    return $a ? app_normalizar($a) : null;
}

function app_por_clave(string $clave): ?array
{
    $a = q1('SELECT * FROM aplicaciones WHERE clave = ?', [$clave]);
    return $a ? app_normalizar($a) : null;
}

/**
 * Rol del usuario en la app, o null si no puede entrar.
 * Ver la regla de acceso en la cabecera del archivo.
 */
function rol_en_app(array $usuario, array $app): ?string
{
    if ((int)($usuario['activo'] ?? 1) !== 1 || $app['estado'] === 'proximamente' || !$app['roles']) {
        return null;
    }
    $super = ($usuario['rol'] ?? '') === 'superadmin';
    if ($app['estado'] === 'mantenimiento' && !$super) {
        return null;
    }
    $rol = qv('SELECT rol FROM permisos WHERE usuario_id = ? AND aplicacion_id = ?', [(int)$usuario['id'], $app['id']]);
    if (is_string($rol) && in_array($rol, $app['roles'], true)) {
        return $rol;
    }
    if ($super) {
        $r = $app['rol_superadmin'] ?? '';
        return ($r !== '' && in_array($r, $app['roles'], true)) ? $r : $app['roles'][0];
    }
    return null;
}

/**
 * Apps que ve el usuario en su panel, con su rol.
 * Un super admin ve además las de 'proximamente' (sin rol, deshabilitadas).
 *
 * @return array<int,array> cada app con la clave extra 'mi_rol' (?string)
 */
function apps_del_usuario(array $usuario): array
{
    $res = [];
    foreach (apps_todas() as $a) {
        $a['mi_rol'] = rol_en_app($usuario, $a);
        if ($a['mi_rol'] !== null || (es_superadmin($usuario) && $a['estado'] === 'proximamente')) {
            $res[] = $a;
        }
    }
    return $res;
}

/** Permisos explícitos de un usuario: [aplicacion_id => rol]. */
function permisos_de(int $usuarioId): array
{
    $r = [];
    foreach (q('SELECT aplicacion_id, rol FROM permisos WHERE usuario_id = ?', [$usuarioId]) as $p) {
        $r[(int)$p['aplicacion_id']] = $p['rol'];
    }
    return $r;
}

/**
 * Aplica los permisos pedidos para un usuario y anota cada cambio.
 *
 * @param array<int,string> $pedidos aplicacion_id => rol ('' = sin acceso)
 * @return array<int,int> ids de las apps a las que se le QUITÓ el acceso
 */
function guardar_permisos(int $usuarioId, string $emailUsuario, array $pedidos, int $porId): array
{
    $actuales = permisos_de($usuarioId);
    $quitadas = [];
    foreach (apps_todas() as $a) {
        if (!array_key_exists($a['id'], $pedidos)) {
            continue;
        }
        $nuevo = (string)$pedidos[$a['id']];
        if ($nuevo !== '' && !in_array($nuevo, $a['roles'], true)) {
            continue;   // rol inventado en el formulario: se ignora
        }
        $antes = $actuales[$a['id']] ?? '';
        if ($nuevo === $antes) {
            continue;
        }
        $t = ahora();
        if ($antes === '') {
            qx('INSERT INTO permisos (usuario_id, aplicacion_id, rol, otorgado_por, creado_en, actualizado_en)
                VALUES (?, ?, ?, ?, ?, ?)', [$usuarioId, $a['id'], $nuevo, $porId, $t, $t]);
            auditar($porId, 'permiso_otorgado', $a['id'], $emailUsuario, ['rol' => $nuevo]);
        } elseif ($nuevo === '') {
            qx('DELETE FROM permisos WHERE usuario_id = ? AND aplicacion_id = ?', [$usuarioId, $a['id']]);
            auditar($porId, 'permiso_quitado', $a['id'], $emailUsuario, ['rol_anterior' => $antes]);
            $quitadas[] = $a['id'];
        } else {
            qx('UPDATE permisos SET rol = ?, otorgado_por = ?, actualizado_en = ? WHERE usuario_id = ? AND aplicacion_id = ?',
                [$nuevo, $porId, $t, $usuarioId, $a['id']]);
            auditar($porId, 'permiso_cambiado', $a['id'], $emailUsuario, ['de' => $antes, 'a' => $nuevo]);
        }
    }
    return $quitadas;
}

/**
 * Valida los datos del formulario de una app. Devuelve [datos limpios, errores].
 *
 * @return array{0:array,1:array<string,string>}
 */
function app_validar(array $in, bool $nueva): array
{
    $err = [];
    $d = [
        'clave'          => mb_strtolower(trim((string)($in['clave'] ?? ''))),
        'nombre'         => trim((string)($in['nombre'] ?? '')),
        'descripcion'    => trim((string)($in['descripcion'] ?? '')),
        'categoria'      => trim((string)($in['categoria'] ?? '')) ?: 'General',
        'url'            => trim((string)($in['url'] ?? '')),
        'icono'          => (string)($in['icono'] ?? 'app'),
        'color'          => (string)($in['color'] ?? 'violeta'),
        'estado'         => (string)($in['estado'] ?? 'activa'),
        'orden'          => (int)($in['orden'] ?? 100),
        'rol_superadmin' => trim((string)($in['rol_superadmin'] ?? '')),
        'redirect_uris'  => implode("\n", app_lineas((string)($in['redirect_uris'] ?? ''))),
        'logout_uris'    => implode("\n", app_lineas((string)($in['logout_uris'] ?? ''))),
        'backchannel_uri'=> trim((string)($in['backchannel_uri'] ?? '')),
    ];
    $roles = array_values(array_unique(array_filter(array_map(
        static fn(string $r): string => mb_strtolower(trim($r)),
        explode(',', (string)($in['roles'] ?? ''))
    ), static fn(string $r): bool => $r !== '')));

    if ($nueva && !preg_match('/^[a-z][a-z0-9-]{1,39}$/', $d['clave'])) {
        $err['clave'] = 'Minúsculas, números y guiones; empieza con letra (2 a 40).';
    }
    if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 80) {
        $err['nombre'] = 'El nombre es obligatorio (hasta 80 caracteres).';
    }
    if (mb_strlen($d['descripcion']) > 255) {
        $err['descripcion'] = 'Hasta 255 caracteres.';
    }
    if (!preg_match('#^https?://#i', $d['url']) || !filter_var($d['url'], FILTER_VALIDATE_URL)) {
        $err['url'] = 'Tiene que ser una dirección completa (https://...).';
    }
    if (!array_key_exists($d['icono'], ICONOS_APP)) {
        $d['icono'] = 'app';
    }
    if (!array_key_exists($d['color'], COLORES_APP)) {
        $d['color'] = 'violeta';
    }
    if (!array_key_exists($d['estado'], ESTADOS_APP)) {
        $err['estado'] = 'Estado inválido.';
    }
    if (!$roles) {
        $err['roles'] = 'Al menos un rol (separados por coma, el de más jerarquía primero).';
    }
    foreach ($roles as $r) {
        if (!preg_match('/^[a-z0-9_-]{1,40}$/', $r)) {
            $err['roles'] = 'Los roles van en minúsculas, sin espacios: ' . $r;
        }
    }
    if ($d['rol_superadmin'] !== '' && !in_array($d['rol_superadmin'], $roles, true)) {
        $err['rol_superadmin'] = 'Tiene que ser uno de los roles de la app.';
    }
    foreach (array_merge(app_lineas($d['redirect_uris']), app_lineas($d['logout_uris'])) as $u) {
        if (!uri_valida_para_oidc($u)) {
            $err['redirect_uris'] = 'Cada URI tiene que ser https:// completa, sin #: ' . $u;
        }
    }
    if ($d['backchannel_uri'] !== '' && !uri_valida_para_oidc($d['backchannel_uri'])) {
        $err['backchannel_uri'] = 'Tiene que ser https:// completa.';
    }
    $d['roles'] = json_encode($roles);
    return [$d, $err];
}

/** URI aceptable para registrar: https (o http sólo a localhost para desarrollo), sin fragmento. */
function uri_valida_para_oidc(string $u): bool
{
    if (!filter_var($u, FILTER_VALIDATE_URL) || str_contains($u, '#')) {
        return false;
    }
    $p = parse_url($u);
    $esquema = strtolower($p['scheme'] ?? '');
    $host = strtolower($p['host'] ?? '');
    return $esquema === 'https' || ($esquema === 'http' && in_array($host, ['localhost', '127.0.0.1'], true));
}
