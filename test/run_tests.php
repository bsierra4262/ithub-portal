<?php
declare(strict_types=1);

// ============================================================
//  Batería de tests de regresión — ITHub
//
//  Prueba la lógica de app/web/app/ llamando a las funciones directamente:
//  sin servidor web, sin MySQL y sin red. Lo que necesita base corre contra
//  una SQLite en memoria armada con el mismo app/db/schema.sql (traducido
//  por sql_a_sqlite()), que se crea y se tira en cada corrida. Los pedidos
//  HTTP salientes (Google, avisos de cierre) se reemplazan por un doble que
//  anota lo que se habría mandado.
//
//  Cómo se corre, desde la raíz del repo:
//    · Windows (PHP sin extensiones cargadas por defecto):
//        php -d extension_dir="$(dirname "$(which php)")/ext" -d extension=openssl \
//            -d extension=mbstring -d extension=pdo_sqlite -d extension=curl test/run_tests.php
//    · Con las extensiones ya cargadas: php test/run_tests.php
//    · Contra el código desplegado: ITHUB_SRC=/ruta/al/webroot/app php run_tests.php
//
//  Salida: una línea [OK]/[XX] por prueba y el resumen. Código de salida 0
//  si todo pasó, 1 si algo falló.
//
//  REGLA DEL PROYECTO: cada defecto que aparece suma un caso acá, para que
//  no pueda volver.
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');

$src = getenv('ITHUB_SRC') ?: dirname(__DIR__) . '/app/web/app';
define('APP', $src);
define('WEB', dirname($src));
define('BASE_PATH', '');

$GLOBALS['CONFIG'] = [
    'app'    => ['url' => 'https://ithub.test', 'env' => 'prod', 'timezone' => 'America/Argentina/Buenos_Aires',
                 'idle_ttl' => 7200, 'abs_ttl' => 43200, 'proxies_confiables' => []],
    'google' => ['client_id' => 'cliente-google', 'client_secret' => 'x', 'allowed_domain' => 'intellihelp.tech',
                 'auto_provision' => true],
    'oidc'   => ['codigo_ttl' => 60, 'id_token_ttl' => 300, 'access_ttl' => 3600],
];
date_default_timezone_set('America/Argentina/Buenos_Aires');

foreach (['helpers', 'db', 'auditoria', 'auth', 'jwt', 'http', 'google', 'aplicaciones', 'oidc', 'iconos'] as $m) {
    require APP . "/$m.php";
}

// ---- Base en memoria ----
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
db_usar($pdo);
$schema = is_file(dirname(WEB) . '/db/schema.sql') ? dirname(WEB) . '/db/schema.sql' : dirname(__DIR__) . '/app/db/schema.sql';
db_ejecutar_sql((string)file_get_contents($schema));

// ---- Clave de firma de prueba ----
$GLOBALS['__oidc_privada'] = generar_clave_rsa();

// ---- Doble de HTTP: anota los POST salientes ----
$GLOBALS['__enviados'] = [];
$GLOBALS['__http_falso'] = static function (string $url, array $campos): array {
    $GLOBALS['__enviados'][] = ['url' => $url, 'campos' => $campos];
    return [200, '{}'];
};

// ---- Mini framework ----
$total = 0;
$fallas = 0;
function t(string $nombre, callable $fn): void
{
    global $total, $fallas;
    $total++;
    try {
        $fn();
        echo "[OK] $nombre\n";
    } catch (Throwable $e) {
        $fallas++;
        echo "[XX] $nombre\n     → " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}
function ok(bool $c, string $msg = 'se esperaba verdadero'): void
{
    if (!$c) {
        throw new RuntimeException($msg);
    }
}
function igual(mixed $esperado, mixed $real, string $msg = ''): void
{
    if ($esperado !== $real) {
        throw new RuntimeException(($msg ? "$msg: " : '') . 'se esperaba ' . var_export($esperado, true) . ', vino ' . var_export($real, true));
    }
}
/** Corre $fn esperando un OidcError con ese código; lo devuelve. */
function falla_oidc(string $codigo, callable $fn): OidcError
{
    try {
        $fn();
    } catch (OidcError $e) {
        igual($codigo, $e->codigo, 'código de error');
        return $e;
    }
    throw new RuntimeException("se esperaba OidcError $codigo y no hubo error");
}
/** id_token "de Google" sin firma (la validación de Google es de claims). */
function token_google(array $c): string
{
    $base = ['iss' => 'https://accounts.google.com', 'aud' => 'cliente-google', 'exp' => time() + 300,
             'nonce' => 'n1', 'email_verified' => true, 'email' => 'ana@intellihelp.tech', 'hd' => 'intellihelp.tech',
             'name' => 'Ana Pérez'];
    return b64url_enc('{"alg":"RS256"}') . '.' . b64url_enc(json_encode(array_merge($base, $c))) . '.' . b64url_enc('firma');
}
function parametros(string $url): array
{
    parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
    return $q;
}

// ---- Datos base ----
$t0 = ahora();
foreach (require APP . '/cli/catalogo.php' as [$clave, $nombre, $desc, $cat, $orden, $url, $icono, $color, $roles]) {
    qx('INSERT INTO aplicaciones (clave, nombre, descripcion, categoria, url, icono, color, estado, orden, roles, rol_superadmin, creado_en, actualizado_en)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$clave, $nombre, $desc, $cat, $url, $icono, $color, 'activa', $orden, json_encode($roles), '', $t0, $t0]);
}
$idSuper = crear_usuario('brian@intellihelp.tech', 'Brian Sierra', 'superadmin');
$idAdmin = crear_usuario('nico@intellihelp.tech', 'Nicolás B', 'admin');
$idUsu = crear_usuario('juan@intellihelp.tech', 'Juan Gómez', 'usuario');
$super = usuario_por_email('brian@intellihelp.tech');
$admin = usuario_por_email('nico@intellihelp.tech');
$usu = usuario_por_email('juan@intellihelp.tech');
$remitos = app_por_clave('remitos');
$crm = app_por_clave('crm');

// Conecta remitos y crm al ingreso único.
$secretoRemitos = oidc_generar_secreto($remitos['id']);
$secretoCrm = oidc_generar_secreto($crm['id']);
qx("UPDATE aplicaciones SET redirect_uris = ?, logout_uris = ?, backchannel_uri = ? WHERE clave = 'remitos'",
    ["https://remitos.intellihelp.tech/ithub-callback\nhttps://remitos.intellihelp.tech/otra", 'https://remitos.intellihelp.tech/login',
     'https://remitos.intellihelp.tech/ithub-logout']);
qx("UPDATE aplicaciones SET redirect_uris = ?, backchannel_uri = ? WHERE clave = 'crm'",
    ['https://intellihelp.tech/crm/ithub-callback', 'https://intellihelp.tech/crm/ithub-logout']);
$remitos = app_por_clave('remitos');
$crm = app_por_clave('crm');

/** Pedido de autorización válido para remitos. */
function pedido_remitos(array $extra = []): array
{
    return array_merge(['client_id' => 'remitos', 'redirect_uri' => 'https://remitos.intellihelp.tech/ithub-callback',
        'response_type' => 'code', 'scope' => 'openid email profile', 'state' => 'st-1', 'nonce' => 'no-1'], $extra);
}

/** Abre sesión, emite código para remitos y lo devuelve con el sid. */
function codigo_para(array $usuario, array $extraPedido = []): array
{
    $_SESSION = [];
    $sid = iniciar_sesion($usuario);
    $pedido = oidc_validar_autorizacion(pedido_remitos($extraPedido));
    $rol = rol_en_app($usuario, $pedido['app']);
    $url = oidc_emitir_codigo($pedido, $usuario, $sid, (string)$rol);
    return [parametros($url)['code'], $sid, $url];
}

echo "\n== JWT y claves ==\n";

t('firma RS256 y verificación con la pública', function () {
    $k = oidc_claves();
    $jwt = jwt_firmar(['sub' => '7', 'x' => 'ñ'], $k['privada'], $k['jwk']['kid']);
    $c = jwt_verificar($jwt, $k['publica']);
    igual('7', $c['sub'] ?? null);
    igual('ñ', $c['x'] ?? null);
});
t('un token alterado no verifica', function () {
    $k = oidc_claves();
    $jwt = jwt_firmar(['sub' => '7'], $k['privada'], $k['jwk']['kid']);
    [$h, , $f] = explode('.', $jwt);
    ok(jwt_verificar($h . '.' . b64url_enc('{"sub":"1"}') . '.' . $f, $k['publica']) === null);
});
t('alg "none" nunca verifica', function () {
    $falso = b64url_enc('{"alg":"none"}') . '.' . b64url_enc('{"sub":"1"}') . '.';
    ok(jwt_verificar($falso, oidc_claves()['publica']) === null);
});
t('JWK con kid estable y sin parte privada', function () {
    $a = oidc_claves()['jwk'];
    $b = jwk_de_privada($GLOBALS['__oidc_privada'])['jwk'];
    igual($a['kid'], $b['kid']);
    igual(['kty', 'use', 'alg', 'kid', 'n', 'e'], array_keys($a));
});

echo "\n== Dominio y Google ==\n";

t('email del dominio: exacto, sin sufijos ni subdominios', function () {
    ok(email_del_dominio('ana@intellihelp.tech', 'intellihelp.tech'));
    ok(email_del_dominio('ANA@Intellihelp.Tech', 'intellihelp.tech'));
    ok(!email_del_dominio('ana@intellihelp.tech.evil.com', 'intellihelp.tech'));
    ok(!email_del_dominio('ana@evilintellihelp.tech', 'intellihelp.tech'));
    ok(!email_del_dominio('ana@sub.intellihelp.tech', 'intellihelp.tech'));
    ok(!email_del_dominio('intellihelp.tech', 'intellihelp.tech'));
});
t('Google: identidad válida', function () {
    $id = google_validar_identidad(token_google([]), 'n1');
    igual('ana@intellihelp.tech', $id['email']);
    igual('Ana Pérez', $id['nombre']);
});
foreach ([
    'sin hd (Gmail personal)'          => ['hd' => null],
    'hd de otro Workspace'             => ['hd' => 'otra.com'],
    'email de otro dominio con hd ok'  => ['email' => 'ana@otra.com'],
    'email no verificado'              => ['email_verified' => false],
    'audiencia de otro cliente'        => ['aud' => 'otro'],
    'emisor falso'                     => ['iss' => 'https://evil.com'],
    'vencido'                          => ['exp' => time() - 3600],
    'nonce distinto'                   => ['nonce' => 'otro'],
] as $caso => $cambio) {
    t("Google rechaza: $caso", function () use ($cambio) {
        $c = array_filter($cambio, static fn($v) => $v !== null);
        $tok = token_google($c);
        if (array_key_exists('hd', $cambio) && $cambio['hd'] === null) {
            $p = explode('.', $tok);
            $claims = json_decode((string)b64url_dec($p[1]), true);
            unset($claims['hd']);
            $tok = $p[0] . '.' . b64url_enc(json_encode($claims)) . '.' . $p[2];
        }
        try {
            google_validar_identidad($tok, 'n1');
        } catch (RuntimeException) {
            return;
        }
        throw new RuntimeException('no rechazó');
    });
}
t('primer ingreso con Google crea el usuario sin acceso a apps', function () {
    $m = null;
    $u = google_resolver_usuario(['email' => 'nueva@intellihelp.tech', 'nombre' => 'Nueva', 'avatar' => null], $m);
    igual('usuario', $u['rol']);
    igual([], permisos_de((int)$u['id']));
    igual([], array_values(array_filter(apps_del_usuario($u), static fn($a) => $a['mi_rol'] !== null)));
});
t('sin auto_provision, una cuenta nueva no entra', function () {
    $GLOBALS['CONFIG']['google']['auto_provision'] = false;
    $m = null;
    ok(google_resolver_usuario(['email' => 'otra@intellihelp.tech', 'nombre' => 'Otra', 'avatar' => null], $m) === null);
    igual('no_registrado', $m);
    $GLOBALS['CONFIG']['google']['auto_provision'] = true;
});
t('un usuario desactivado no entra', function () {
    $id = crear_usuario('baja@intellihelp.tech', 'Baja', 'usuario');
    qx('UPDATE usuarios SET activo = 0 WHERE id = ?', [$id]);
    $m = null;
    ok(google_resolver_usuario(['email' => 'baja@intellihelp.tech', 'nombre' => 'Baja', 'avatar' => null], $m) === null);
    igual('inactivo', $m);
});

echo "\n== Permisos por aplicación ==\n";

t('sin permiso no entra; con permiso entra con su rol', function () use ($usu, $remitos, $super, $admin) {
    ok(rol_en_app($usu, $remitos) === null);
    guardar_permisos($usu['id'], $usu['email'], [$remitos['id'] => 'operador'], $super['id']);
    igual('operador', rol_en_app($usu, $remitos));
    ok(rol_en_app($admin, $remitos) === null, 'un admin del portal no entra a las apps sin permiso');
});
t('super admin entra a todas con el primer rol (o rol_superadmin)', function () use ($super, $crm) {
    igual('admin', rol_en_app($super, $crm));
    qx("UPDATE aplicaciones SET rol_superadmin = 'comercial' WHERE id = ?", [$crm['id']]);
    igual('comercial', rol_en_app($super, app_por_id($crm['id'])));
    qx("UPDATE aplicaciones SET rol_superadmin = '' WHERE id = ?", [$crm['id']]);
});
t('mantenimiento: sólo super admin; próximamente: nadie', function () use ($super, $usu, $remitos) {
    qx("UPDATE aplicaciones SET estado = 'mantenimiento' WHERE id = ?", [$remitos['id']]);
    ok(rol_en_app($usu, app_por_id($remitos['id'])) === null);
    igual('admin', rol_en_app($super, app_por_id($remitos['id'])));
    qx("UPDATE aplicaciones SET estado = 'proximamente' WHERE id = ?", [$remitos['id']]);
    ok(rol_en_app($super, app_por_id($remitos['id'])) === null);
    qx("UPDATE aplicaciones SET estado = 'activa' WHERE id = ?", [$remitos['id']]);
});
t('un rol que la app ya no tiene deja de valer', function () use ($usu, $remitos) {
    qx("UPDATE aplicaciones SET roles = ? WHERE id = ?", ['["admin"]', $remitos['id']]);
    ok(rol_en_app($usu, app_por_id($remitos['id'])) === null);
    qx("UPDATE aplicaciones SET roles = ? WHERE id = ?", ['["admin","operador"]', $remitos['id']]);
    igual('operador', rol_en_app($usu, app_por_id($remitos['id'])));
});
t('guardar permisos: otorga, cambia, quita, ignora roles inventados y audita', function () use ($usu, $crm, $super) {
    $antes = (int)qv('SELECT COUNT(*) FROM auditoria');
    igual([], guardar_permisos($usu['id'], $usu['email'], [$crm['id'] => 'inventado'], $super['id']));
    ok(!isset(permisos_de($usu['id'])[$crm['id']]));
    guardar_permisos($usu['id'], $usu['email'], [$crm['id'] => 'comercial'], $super['id']);
    guardar_permisos($usu['id'], $usu['email'], [$crm['id'] => 'admin'], $super['id']);
    igual([$crm['id']], guardar_permisos($usu['id'], $usu['email'], [$crm['id'] => ''], $super['id']));
    igual(3, (int)qv('SELECT COUNT(*) FROM auditoria') - $antes);
    igual(['permiso_quitado', 'permiso_cambiado', 'permiso_otorgado'],
        array_column(q('SELECT accion FROM auditoria ORDER BY id DESC LIMIT 3'), 'accion'));
});
t('el panel muestra sólo las apps con acceso; al super admin, también las próximas', function () use ($usu, $super) {
    $mias = array_column(array_filter(apps_del_usuario($usu), static fn($a) => $a['mi_rol'] !== null), 'clave');
    igual(['remitos'], array_values($mias));
    qx("UPDATE aplicaciones SET estado = 'proximamente' WHERE clave = 'tareas'");
    $deSuper = array_column(apps_del_usuario($super), 'mi_rol', 'clave');
    ok(array_key_exists('tareas', $deSuper) && $deSuper['tareas'] === null);
    ok(!in_array('tareas', array_column(apps_del_usuario($usu), 'clave'), true));
    qx("UPDATE aplicaciones SET estado = 'activa' WHERE clave = 'tareas'");
});
t('un admin no edita a un super admin; un super admin edita a todos', function () use ($admin, $super, $usu) {
    ok(!puede_editar_usuario($admin, $super));
    ok(puede_editar_usuario($admin, $usu));
    ok(puede_editar_usuario($super, $admin));
    ok(!puede_editar_usuario($usu, $usu));
});

echo "\n== Catálogo de aplicaciones ==\n";

t('validación: clave, URL, roles y URIs', function () {
    [, $e] = app_validar(['clave' => 'Mal Clave', 'nombre' => '', 'url' => 'ftp://x', 'roles' => ''], true);
    ok(isset($e['clave'], $e['nombre'], $e['url'], $e['roles']));
    [, $e] = app_validar(['clave' => 'ok', 'nombre' => 'Ok', 'url' => 'https://ok.tech', 'roles' => 'admin',
        'redirect_uris' => "https://ok.tech/cb#frag"], true);
    ok(isset($e['redirect_uris']), 'URI con fragmento');
    [, $e] = app_validar(['clave' => 'ok', 'nombre' => 'Ok', 'url' => 'https://ok.tech', 'roles' => 'admin',
        'redirect_uris' => "http://ok.tech/cb"], true);
    ok(isset($e['redirect_uris']), 'http fuera de localhost');
    [$d, $e] = app_validar(['clave' => 'ok-2', 'nombre' => 'Ok', 'url' => 'https://ok.tech', 'roles' => ' Admin , operador,admin ',
        'redirect_uris' => "https://ok.tech/cb\n\n http://localhost:8080/cb ", 'rol_superadmin' => 'operador'], true);
    igual([], $e);
    igual('["admin","operador"]', $d['roles']);
    igual("https://ok.tech/cb\nhttp://localhost:8080/cb", $d['redirect_uris']);
});
t('rol de super admin tiene que existir en la app', function () {
    [, $e] = app_validar(['clave' => 'ok', 'nombre' => 'Ok', 'url' => 'https://ok.tech', 'roles' => 'admin', 'rol_superadmin' => 'otro'], true);
    ok(isset($e['rol_superadmin']));
});
t('regenerar el secreto invalida el anterior', function () use ($crm) {
    $viejo = oidc_generar_secreto($crm['id']);
    $nuevo = oidc_generar_secreto($crm['id']);
    ok(str_starts_with($nuevo, 'ith_') && $viejo !== $nuevo);
    falla_oidc('invalid_client', fn() => oidc_autenticar_cliente([], ['client_id' => 'crm', 'client_secret' => $viejo]));
    igual('crm', oidc_autenticar_cliente([], ['client_id' => 'crm', 'client_secret' => $nuevo])['clave']);
    $GLOBALS['secretoCrm'] = $nuevo;
});

echo "\n== OpenID Connect: autorización ==\n";

t('app desconocida o sin secreto: error en ITHub, sin redirigir', function () {
    $e = falla_oidc('invalid_client', fn() => oidc_validar_autorizacion(pedido_remitos(['client_id' => 'nadie'])));
    ok(!$e->redirigible);
    $e = falla_oidc('invalid_client', fn() => oidc_validar_autorizacion(pedido_remitos(['client_id' => 'tareas'])));
    ok(!$e->redirigible);
});
t('redirect_uri: sólo coincidencia exacta (sin prefijos ni variantes)', function () {
    foreach (['https://remitos.intellihelp.tech/ithub-callback/../x', 'https://remitos.intellihelp.tech/ithub-callbackx',
              'https://remitos.intellihelp.tech/ithub-callback?x=1', 'https://evil.com/ithub-callback',
              'https://REMITOS.intellihelp.tech/ithub-callback', ''] as $u) {
        $e = falla_oidc('invalid_request', fn() => oidc_validar_autorizacion(pedido_remitos(['redirect_uri' => $u])));
        ok(!$e->redirigible, "no debería volver a $u");
    }
    igual('https://remitos.intellihelp.tech/otra',
        oidc_validar_autorizacion(pedido_remitos(['redirect_uri' => 'https://remitos.intellihelp.tech/otra']))['redirect_uri']);
});
t('errores del resto del pedido vuelven a la app con state e iss', function () {
    $e = falla_oidc('unsupported_response_type', fn() => oidc_validar_autorizacion(pedido_remitos(['response_type' => 'token'])));
    ok($e->redirigible);
    $q = parametros(oidc_url_error($e->pedido, $e->codigo, $e->getMessage()));
    igual('st-1', $q['state']);
    igual('https://ithub.test', $q['iss']);
    falla_oidc('invalid_scope', fn() => oidc_validar_autorizacion(pedido_remitos(['scope' => 'email'])));
    falla_oidc('invalid_request', fn() => oidc_validar_autorizacion(pedido_remitos(['code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'plain'])));
});
t('scopes desconocidos se descartan', function () {
    igual('openid email', oidc_validar_autorizacion(pedido_remitos(['scope' => 'openid admin email']))['scope']);
});

echo "\n== OpenID Connect: canje e id_token ==\n";

t('flujo completo: código, canje, id_token firmado con rol y sid', function () use ($usu, $secretoRemitos) {
    [$codigo, $sid, $url] = codigo_para($usu);
    $q = parametros($url);
    igual('st-1', $q['state']);
    igual('https://ithub.test', $q['iss']);
    $app = oidc_autenticar_cliente(['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('remitos:' . $secretoRemitos)], []);
    $r = oidc_canjear_codigo($app, ['grant_type' => 'authorization_code', 'code' => $codigo,
        'redirect_uri' => 'https://remitos.intellihelp.tech/ithub-callback']);
    igual('Bearer', $r['token_type']);
    $c = jwt_verificar($r['id_token'], oidc_claves()['publica']);
    ok($c !== null, 'firma del id_token');
    igual('https://ithub.test', $c['iss']);
    igual('remitos', $c['aud']);
    igual((string)$usu['id'], $c['sub']);
    igual('juan@intellihelp.tech', $c['email']);
    igual('operador', $c['rol']);
    igual('no-1', $c['nonce']);
    igual($sid, $c['sid']);
    igual('intellihelp.tech', $c['hd']);
    ok($c['exp'] > time() && $c['exp'] <= time() + 300);
    $ui = oidc_userinfo($r['access_token']);
    igual('operador', $ui['rol']);
    igual('Juan', $ui['given_name']);
    $GLOBALS['ultimo'] = ['codigo' => $codigo, 'access' => $r['access_token'], 'app' => $app];
});
t('reusar un código falla y revoca los tokens que dio', function () {
    $u = $GLOBALS['ultimo'];
    falla_oidc('invalid_grant', fn() => oidc_canjear_codigo($u['app'], ['grant_type' => 'authorization_code', 'code' => $u['codigo'],
        'redirect_uri' => 'https://remitos.intellihelp.tech/ithub-callback']));
    falla_oidc('invalid_token', fn() => oidc_userinfo($u['access']));
});
t('código vencido, de otra app, o con otra redirect_uri: invalid_grant', function () use ($usu, $secretoRemitos) {
    $app = oidc_autenticar_cliente([], ['client_id' => 'remitos', 'client_secret' => $secretoRemitos]);
    $base = ['grant_type' => 'authorization_code', 'redirect_uri' => 'https://remitos.intellihelp.tech/ithub-callback'];
    [$c1] = codigo_para($usu);
    qx('UPDATE oidc_codigos SET expira_en = ? WHERE codigo_hash = ?', [ahora(-1), hash('sha256', $c1)]);
    falla_oidc('invalid_grant', fn() => oidc_canjear_codigo($app, $base + ['code' => $c1]));
    [$c2] = codigo_para($usu);
    $crmApp = oidc_autenticar_cliente([], ['client_id' => 'crm', 'client_secret' => $GLOBALS['secretoCrm']]);
    falla_oidc('invalid_grant', fn() => oidc_canjear_codigo($crmApp, $base + ['code' => $c2]));
    [$c3] = codigo_para($usu);
    falla_oidc('invalid_grant', fn() => oidc_canjear_codigo($app, ['code' => $c3, 'grant_type' => 'authorization_code',
        'redirect_uri' => 'https://remitos.intellihelp.tech/otra']));
    falla_oidc('unsupported_grant_type', fn() => oidc_canjear_codigo($app, ['grant_type' => 'password']));
});
t('credenciales de la app: secreto incorrecto o enviadas dos veces', function () use ($secretoRemitos) {
    $e = falla_oidc('invalid_client', fn() => oidc_autenticar_cliente([], ['client_id' => 'remitos', 'client_secret' => 'mal']));
    igual(401, $e->http);
    falla_oidc('invalid_client', fn() => oidc_autenticar_cliente([], ['client_id' => 'remitos']));
    falla_oidc('invalid_request', fn() => oidc_autenticar_cliente(
        ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('remitos:' . $secretoRemitos)], ['client_secret' => $secretoRemitos]));
});
t('PKCE S256: sin verificador o con uno incorrecto falla; el correcto entra', function () use ($usu, $secretoRemitos) {
    $ver = b64url_enc(random_bytes(40));
    $cc = b64url_enc(hash('sha256', $ver, true));
    $app = oidc_autenticar_cliente([], ['client_id' => 'remitos', 'client_secret' => $secretoRemitos]);
    $base = ['grant_type' => 'authorization_code', 'redirect_uri' => 'https://remitos.intellihelp.tech/ithub-callback'];
    [$c] = codigo_para($usu, ['code_challenge' => $cc, 'code_challenge_method' => 'S256']);
    falla_oidc('invalid_grant', fn() => oidc_canjear_codigo($app, $base + ['code' => $c, 'code_verifier' => 'otro']));
    [$c] = codigo_para($usu, ['code_challenge' => $cc, 'code_challenge_method' => 'S256']);
    ok(isset(oidc_canjear_codigo($app, $base + ['code' => $c, 'code_verifier' => $ver])['id_token']));
});
t('si la sesión se cerró antes del canje, no hay tokens', function () use ($usu, $secretoRemitos) {
    $app = oidc_autenticar_cliente([], ['client_id' => 'remitos', 'client_secret' => $secretoRemitos]);
    [$c, $sid] = codigo_para($usu);
    cerrar_sesion($sid, 'salida', $usu['id']);
    falla_oidc('invalid_grant', fn() => oidc_canjear_codigo($app, ['grant_type' => 'authorization_code', 'code' => $c,
        'redirect_uri' => 'https://remitos.intellihelp.tech/ithub-callback']));
});

echo "\n== Cierre de sesión en todas las apps ==\n";

t('cerrar la sesión avisa a cada app con un logout_token firmado', function () use ($super, $secretoRemitos) {
    $_SESSION = [];
    $sid = iniciar_sesion($super);
    foreach (['remitos' => 'https://remitos.intellihelp.tech/ithub-callback', 'crm' => 'https://intellihelp.tech/crm/ithub-callback'] as $cl => $ru) {
        $p = oidc_validar_autorizacion(['client_id' => $cl, 'redirect_uri' => $ru, 'response_type' => 'code', 'scope' => 'openid']);
        oidc_emitir_codigo($p, $super, $sid, (string)rol_en_app($super, $p['app']));
    }
    $GLOBALS['__enviados'] = [];
    ok(cerrar_sesion($sid, 'revocada', $super['id']));
    $urls = array_column($GLOBALS['__enviados'], 'url');
    sort($urls);
    igual(['https://intellihelp.tech/crm/ithub-logout', 'https://remitos.intellihelp.tech/ithub-logout'], $urls);
    $c = jwt_verificar($GLOBALS['__enviados'][0]['campos']['logout_token'], oidc_claves()['publica']);
    ok($c !== null, 'firma del logout_token');
    igual($sid, $c['sid']);
    ok(isset($c['events'][OIDC_EVENTO_LOGOUT]) && !isset($c['nonce']));
    // La especificación pide un objeto vacío ({}), no una lista vacía ([]).
    $crudo = (string)b64url_dec(explode('.', $GLOBALS['__enviados'][0]['campos']['logout_token'])[1]);
    ok(str_contains($crudo, 'backchannel-logout":{}'), 'events tiene que ser un objeto vacío: ' . $crudo);
    ok(!cerrar_sesion($sid, 'revocada', $super['id']), 'cerrarla de nuevo no avisa otra vez');
});
t('quitar una app a alguien cierra su sesión sólo en esa app', function () use ($usu, $super, $remitos, $crm, $secretoRemitos) {
    guardar_permisos($usu['id'], $usu['email'], [$crm['id'] => 'comercial'], $super['id']);
    $_SESSION = [];
    $sid = iniciar_sesion($usu);
    foreach (['remitos' => 'https://remitos.intellihelp.tech/ithub-callback', 'crm' => 'https://intellihelp.tech/crm/ithub-callback'] as $cl => $ru) {
        $p = oidc_validar_autorizacion(['client_id' => $cl, 'redirect_uri' => $ru, 'response_type' => 'code', 'scope' => 'openid']);
        oidc_emitir_codigo($p, $usu, $sid, (string)rol_en_app($usu, $p['app']));
    }
    $GLOBALS['__enviados'] = [];
    foreach (guardar_permisos($usu['id'], $usu['email'], [$crm['id'] => ''], $super['id']) as $appId) {
        oidc_revocar_app_usuario($usu['id'], $appId);
    }
    igual(['https://intellihelp.tech/crm/ithub-logout'], array_column($GLOBALS['__enviados'], 'url'));
    igual(1, (int)qv('SELECT COUNT(*) FROM sesion_apps WHERE sesion_id = ?', [$sid]), 'sigue en remitos');
    ok(qv('SELECT cerrada_en FROM sesiones WHERE id = ?', [$sid]) === null, 'la sesión de ITHub sigue abierta');
});
t('desactivar a alguien cierra todas sus sesiones', function () use ($usu, $super) {
    $_SESSION = [];
    iniciar_sesion($usu);
    $abiertas = (int)qv('SELECT COUNT(*) FROM sesiones WHERE usuario_id = ? AND cerrada_en IS NULL', [$usu['id']]);
    ok($abiertas >= 1);
    igual($abiertas, cerrar_sesiones_usuario($usu['id'], 'usuario_inactivo', $super['id']));
    igual(0, (int)qv('SELECT COUNT(*) FROM sesiones WHERE usuario_id = ? AND cerrada_en IS NULL', [$usu['id']]));
});
t('id_token_hint: válido si lo firmó ITHub; alterado no', function () use ($usu, $remitos) {
    $hint = oidc_id_token($remitos, $usu, 'sid-x', null, 'operador', 'openid', time());
    igual('remitos', oidc_validar_hint($hint)['aud'] ?? null);
    [$h, $p, $f] = explode('.', $hint);
    ok(oidc_validar_hint($h . '.' . b64url_enc('{"iss":"https://ithub.test","sub":"1"}') . '.' . $f) === null);
});

echo "\n== Navegación y descubrimiento ==\n";

t('volver después del login: sólo rutas internas', function () {
    foreach (['//evil.com/x', '/\\evil.com', 'https://evil.com', 'javascript:alert(1)'] as $malo) {
        $_SESSION = ['volver' => $malo];
        igual('/panel', destino_post_login(), "no tiene que volver a $malo");
    }
    $_SESSION = ['volver' => '/usuarios?q=ana'];
    igual('/usuarios?q=ana', destino_post_login());
    $_SESSION = ['oidc_pendiente' => 'client_id=crm&state=1', 'oidc_pendiente_app' => 'CRM'];
    igual('/oauth/authorize?client_id=crm&state=1', destino_post_login());
    ok(!isset($_SESSION['oidc_pendiente']));
});
t('discovery: endpoints bajo el issuer y sólo RS256 + code', function () {
    $d = oidc_discovery();
    igual('https://ithub.test', $d['issuer']);
    foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri', 'end_session_endpoint'] as $k) {
        ok(str_starts_with($d[$k], 'https://ithub.test/'), $k);
    }
    igual(['RS256'], $d['id_token_signing_alg_values_supported']);
    igual(['code'], $d['response_types_supported']);
    ok($d['backchannel_logout_supported'] === true);
});
t('íconos: todos los del catálogo existen', function () {
    foreach (require APP . '/cli/catalogo.php' as $a) {
        ok(array_key_exists($a[6], ICONOS_APP), 'ícono ' . $a[6]);
        ok(array_key_exists($a[7], COLORES_APP), 'color ' . $a[7]);
    }
    ok(str_contains(icono('no-existe'), '<svg'));
});
t('auditoría: no guarda secretos ni códigos', function () {
    $todo = implode("\n", array_map(static fn($f) => (string)$f['detalle'] . ' ' . (string)$f['objetivo'], q('SELECT detalle, objetivo FROM auditoria')));
    ok(!str_contains($todo, 'ith_'), 'secreto de app en la auditoría');
    ok(!str_contains($todo, (string)($GLOBALS['ultimo']['codigo'] ?? 'zzz')), 'código en la auditoría');
});

echo "\n$total pruebas · " . ($total - $fallas) . " correctas · $fallas fallidas\n";
exit($fallas === 0 ? 0 : 1);
