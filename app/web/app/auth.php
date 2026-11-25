<?php
declare(strict_types=1);

// ============================================================
//  Sesión única de ITHub y roles del portal.
//
//  No hay contraseñas: se entra sólo con Google Workspace (google.php).
//  Después del ingreso, la sesión vive en dos lugares:
//    · la sesión PHP del navegador guarda únicamente `sid`;
//    · la tabla `sesiones` guarda el estado real (abierta, cerrada, por qué,
//      última actividad). Así un admin puede cerrar la sesión de otra persona
//      desde el panel y rige en el pedido siguiente, y el cierre se avisa a
//      todas las apps a las que se entró con esa sesión (oidc_avisar_cierre).
//
//  Roles del portal (ROLES_PORTAL):
//    superadmin  todo, incluido el catálogo de aplicaciones; entra a todas
//                las apps aunque no tenga un permiso explícito.
//    admin       usuarios, permisos, sesiones y auditoría. No puede tocar a
//                un super admin ni nombrar a nadie super admin.
//    usuario     su panel con las apps que tiene asignadas y su cuenta.
// ============================================================

const ROLES_PORTAL = [
    'superadmin' => 'Super admin',
    'admin'      => 'Administrador',
    'usuario'    => 'Usuario',
];

/**
 * Usuario de la sesión actual, o null.
 *
 * Valida contra la base en cada pedido: sesión abierta, usuario activo,
 * inactividad (app.idle_ttl) y duración máxima (app.abs_ttl). Si algo no da,
 * cierra la sesión (y la avisa a las apps) y devuelve null.
 *
 * @return array{id:int,email:string,nombre:string,avatar_url:?string,rol:string,activo:int,sid:string}|null
 */
function usuario_actual(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $sid = $_SESSION['sid'] ?? '';
    if (!is_string($sid) || $sid === '') {
        return $cache = null;
    }
    $f = q1('SELECT s.id AS sid, s.creada_en, s.ultima_actividad, s.cerrada_en,
                    u.id, u.email, u.nombre, u.avatar_url, u.rol, u.activo
             FROM sesiones s JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.id = ?', [$sid]);

    $motivo = null;
    if (!$f || $f['cerrada_en'] !== null) {
        salir_local();
        return $cache = null;
    }
    if ((int)$f['activo'] !== 1) {
        $motivo = 'usuario_inactivo';
    } elseif (strtotime($f['ultima_actividad']) + (int)cfg('app.idle_ttl', 7200) < time()
           || strtotime($f['creada_en']) + (int)cfg('app.abs_ttl', 43200) < time()) {
        $motivo = 'vencida';
    }
    if ($motivo !== null) {
        cerrar_sesion($sid, $motivo, null);
        salir_local();
        return $cache = null;
    }
    // La actividad se anota a lo sumo una vez por minuto.
    if (strtotime($f['ultima_actividad']) < time() - 60) {
        qx('UPDATE sesiones SET ultima_actividad = ? WHERE id = ?', [ahora(), $sid]);
    }
    $f['id'] = (int)$f['id'];
    $f['activo'] = (int)$f['activo'];
    return $cache = $f;
}

/**
 * Abre la sesión de ITHub para un usuario ya validado por Google.
 * Devuelve el id de la sesión.
 */
function iniciar_sesion(array $usuario): string
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $sid = aleatorio_hex(16);
    $t = ahora();
    qx('INSERT INTO sesiones (id, usuario_id, creada_en, ultima_actividad, ip, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)', [
        $sid, (int)$usuario['id'], $t, $t,
        PHP_SAPI === 'cli' ? null : ip_cliente(),
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    qx('UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?', [$t, (int)$usuario['id']]);
    $_SESSION['sid'] = $sid;
    auditar((int)$usuario['id'], 'login', null, $usuario['email']);
    return $sid;
}

/**
 * Cierra una sesión de ITHub en la base y se lo avisa a las apps a las que
 * se entró con ella. No toca la cookie del navegador: eso es salir_local().
 *
 * @return bool true si la sesión estaba abierta y se cerró ahora
 */
function cerrar_sesion(string $sid, string $motivo, ?int $porUsuarioId): bool
{
    $n = qx('UPDATE sesiones SET cerrada_en = ?, motivo_cierre = ? WHERE id = ? AND cerrada_en IS NULL',
        [ahora(), $motivo, $sid]);
    if ($n === 0) {
        return false;
    }
    // Los access tokens de esta sesión dejan de servir en el acto.
    qx('DELETE FROM oidc_tokens WHERE sesion_id = ?', [$sid]);
    oidc_avisar_cierre($sid);
    return true;
}

/** Cierra todas las sesiones abiertas de un usuario. Devuelve cuántas. */
function cerrar_sesiones_usuario(int $usuarioId, string $motivo, ?int $porUsuarioId, ?string $salvo = null): int
{
    $n = 0;
    foreach (q('SELECT id FROM sesiones WHERE usuario_id = ? AND cerrada_en IS NULL', [$usuarioId]) as $s) {
        if ($s['id'] !== $salvo && cerrar_sesion($s['id'], $motivo, $porUsuarioId)) {
            $n++;
        }
    }
    return $n;
}

/** Olvida la sesión en este navegador (cookie y datos de PHP). */
function salir_local(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies') && PHP_SAPI !== 'cli') {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}

// ---- Roles ----

function es_superadmin(?array $u): bool
{
    return $u !== null && $u['rol'] === 'superadmin';
}

/** ¿Puede administrar usuarios, permisos, sesiones y auditoría? */
function es_admin(?array $u): bool
{
    return $u !== null && in_array($u['rol'], ['superadmin', 'admin'], true);
}

/**
 * ¿$actor puede editar a $objetivo? Un admin no toca a un super admin; un
 * super admin toca a todos.
 */
function puede_editar_usuario(array $actor, array $objetivo): bool
{
    if (es_superadmin($actor)) {
        return true;
    }
    return es_admin($actor) && $objetivo['rol'] !== 'superadmin';
}

/** Cuántos super admin activos quedan (para no dejar el portal sin dueño). */
function superadmins_activos(): int
{
    return (int)qv("SELECT COUNT(*) FROM usuarios WHERE rol = 'superadmin' AND activo = 1");
}

/** ¿El email es del dominio permitido? Compara el dominio exacto, no un sufijo. */
function email_del_dominio(string $email, string $dominio): bool
{
    $email = mb_strtolower(trim($email));
    $at = strrpos($email, '@');
    return $at !== false
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && substr($email, $at + 1) === mb_strtolower($dominio);
}

/** Busca un usuario por email (sin importar mayúsculas). */
function usuario_por_email(string $email): ?array
{
    return q1('SELECT * FROM usuarios WHERE email = ?', [mb_strtolower(trim($email))]);
}

/** Crea un usuario y devuelve su id. */
function crear_usuario(string $email, string $nombre, string $rol = 'usuario', ?string $avatar = null): int
{
    $t = ahora();
    qx('INSERT INTO usuarios (email, nombre, avatar_url, rol, activo, creado_en, actualizado_en)
        VALUES (?, ?, ?, ?, 1, ?, ?)', [mb_strtolower(trim($email)), trim($nombre), $avatar, $rol, $t, $t]);
    return qid();
}

/**
 * Adónde ir después del login: un pedido de OpenID Connect que quedó a mitad
 * de camino, la pantalla interna que se quería ver, o el panel.
 */
function destino_post_login(): string
{
    if (!empty($_SESSION['oidc_pendiente'])) {
        $qs = $_SESSION['oidc_pendiente'];
        unset($_SESSION['oidc_pendiente'], $_SESSION['oidc_pendiente_app']);
        return url('oauth/authorize') . '?' . $qs;
    }
    $v = (string)($_SESSION['volver'] ?? '');
    unset($_SESSION['volver']);
    // Sólo rutas internas: empieza con "/" pero no con "//" (otro sitio).
    if ($v !== '' && $v[0] === '/' && !str_starts_with($v, '//') && !str_contains($v, '\\')) {
        return $v;
    }
    return url('panel');
}
