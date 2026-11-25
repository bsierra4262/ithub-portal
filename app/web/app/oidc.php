<?php
declare(strict_types=1);

// ============================================================
//  ITHub como proveedor de identidad: OpenID Connect (Authorization Code).
//
//  Es lo que permite entrar a todas las apps con la misma sesión. Flujo:
//
//    1. La app manda al navegador a /oauth/authorize?client_id=crm&...
//    2. ITHub valida la app y la URI de vuelta (oidc_validar_autorizacion).
//       Sin sesión → login con Google y se retoma solo. Con sesión → mira el
//       permiso (rol_en_app) y, si tiene, emite un código de un solo uso
//       (oidc_emitir_codigo) y vuelve a la app.
//    3. La app, desde su servidor, canjea el código en /oauth/token con su
//       secreto (oidc_autenticar_cliente + oidc_canjear_codigo) y recibe un
//       id_token firmado con: quién es (sub, email, nombre) y su `rol` en
//       ESA app.
//    4. Cuando la sesión de ITHub se cierra (salida, un admin la revoca, el
//       usuario se da de baja), se avisa a cada app con un logout_token
//       (oidc_avisar_cierre, "back-channel logout").
//
//  Endpoints (index.php): /.well-known/openid-configuration, /oauth/authorize,
//  /oauth/token, /oauth/userinfo, /oauth/jwks, /oauth/logout.
//  Guía para conectar una app: md/INTEGRAR-APLICACION.md.
//
//  Decisiones:
//    · Coincidencia EXACTA de redirect_uri (sin prefijos ni comodines).
//    · Código de 60 s y de un solo uso; si se intenta usar dos veces, se
//      revocan los access tokens de esa sesión en esa app (RFC 6749 4.1.2).
//    · PKCE S256 opcional (lo exige si la app mandó code_challenge).
//    · Los secretos de las apps y los códigos/tokens se guardan como SHA-256:
//      una copia de la base no sirve para hacerse pasar por nadie.
// ============================================================

/** Error de protocolo. $redirigible: se informa volviendo a la app (si no, pantalla de error). */
final class OidcError extends RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $descripcion,
        public readonly bool $redirigible = false,
        public readonly int $http = 400,
        public readonly ?array $pedido = null,
    ) {
        parent::__construct($descripcion);
    }
}

const OIDC_SCOPES = ['openid', 'email', 'profile'];
const OIDC_EVENTO_LOGOUT = 'http://schemas.openid.net/event/backchannel-logout';

function oidc_issuer(): string
{
    return rtrim((string)cfg('app.url', ''), '/');
}

/**
 * Clave de firma: ['privada' => PEM, 'publica' => PEM, 'jwk' => array].
 * Los tests la inyectan en $GLOBALS['__oidc_privada'].
 */
function oidc_claves(): array
{
    static $cache = null;
    $pem = $GLOBALS['__oidc_privada'] ?? null;
    if ($pem === null) {
        if ($cache !== null) {
            return $cache;
        }
        $ruta = (string)cfg('oidc.clave_privada', '');
        if ($ruta === '' || !is_readable($ruta)) {
            throw new RuntimeException('Falta la clave de firma de OpenID Connect (oidc.clave_privada). Correr app/instalar.php.');
        }
        $pem = (string)file_get_contents($ruta);
    }
    $k = jwk_de_privada($pem);
    $r = ['privada' => $pem, 'publica' => $k['publica'], 'jwk' => $k['jwk']];
    if (!isset($GLOBALS['__oidc_privada'])) {
        $cache = $r;
    }
    return $r;
}

/** Documento de descubrimiento (/.well-known/openid-configuration). */
function oidc_discovery(): array
{
    return [
        'issuer'                                => oidc_issuer(),
        'authorization_endpoint'                => url_abs('oauth/authorize'),
        'token_endpoint'                        => url_abs('oauth/token'),
        'userinfo_endpoint'                     => url_abs('oauth/userinfo'),
        'jwks_uri'                              => url_abs('oauth/jwks'),
        'end_session_endpoint'                  => url_abs('oauth/logout'),
        'response_types_supported'              => ['code'],
        'response_modes_supported'              => ['query'],
        'grant_types_supported'                 => ['authorization_code'],
        'subject_types_supported'               => ['public'],
        'id_token_signing_alg_values_supported' => ['RS256'],
        'scopes_supported'                      => OIDC_SCOPES,
        'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
        'code_challenge_methods_supported'      => ['S256'],
        'claims_supported'                      => ['iss', 'sub', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'sid',
                                                    'email', 'email_verified', 'name', 'given_name', 'family_name',
                                                    'picture', 'hd', 'rol', 'roles', 'rol_portal'],
        'prompt_values_supported'               => ['none', 'login', 'select_account'],
        'backchannel_logout_supported'          => true,
        'backchannel_logout_session_supported'  => true,
    ];
}

/**
 * Valida el pedido de /oauth/authorize.
 *
 * Primero la app y la URI de vuelta: si cualquiera de las dos está mal, el
 * error se muestra en ITHub (redirigir a una URI no registrada sería un
 * redirector abierto). Del resto se avisa volviendo a la app.
 *
 * @return array{app:array,redirect_uri:string,state:?string,scope:string,nonce:?string,code_challenge:?string,prompt:string}
 * @throws OidcError
 */
function oidc_validar_autorizacion(array $q): array
{
    $app = null;
    $clientId = (string)($q['client_id'] ?? '');
    if ($clientId !== '') {
        $app = app_por_clave($clientId);
    }
    if ($app === null || !$app['sso']) {
        throw new OidcError('invalid_client', 'La aplicación no está registrada en ITHub o no tiene el ingreso único configurado.');
    }
    $redirect = (string)($q['redirect_uri'] ?? '');
    if (!in_array($redirect, $app['redirect_uris_lista'], true)) {
        throw new OidcError('invalid_request', 'La dirección de vuelta no está registrada para esta aplicación.');
    }

    $state = isset($q['state']) ? (string)$q['state'] : null;
    $base = ['app' => $app, 'redirect_uri' => $redirect, 'state' => $state];

    if (($q['response_type'] ?? '') !== 'code') {
        throw oidc_error_redirigible($base, 'unsupported_response_type', 'Sólo se admite response_type=code.');
    }
    $scopes = array_values(array_filter(explode(' ', (string)($q['scope'] ?? ''))));
    if (!in_array('openid', $scopes, true)) {
        throw oidc_error_redirigible($base, 'invalid_scope', 'Falta el scope openid.');
    }
    $cc = (string)($q['code_challenge'] ?? '');
    if ($cc !== '') {
        if (($q['code_challenge_method'] ?? '') !== 'S256') {
            throw oidc_error_redirigible($base, 'invalid_request', 'Sólo se acepta PKCE con S256.');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{43,128}$/', $cc)) {
            throw oidc_error_redirigible($base, 'invalid_request', 'code_challenge inválido.');
        }
    }
    $nonce = isset($q['nonce']) ? (string)$q['nonce'] : null;
    if ($nonce !== null && strlen($nonce) > 255) {
        throw oidc_error_redirigible($base, 'invalid_request', 'nonce demasiado largo.');
    }
    return $base + [
        'scope'          => implode(' ', array_values(array_intersect(OIDC_SCOPES, $scopes))),
        'nonce'          => $nonce,
        'code_challenge' => $cc !== '' ? $cc : null,
        'prompt'         => (string)($q['prompt'] ?? ''),
    ];
}

/** Arma un OidcError que se informa volviendo a la app (lleva el pedido para armar la URL). */
function oidc_error_redirigible(array $pedido, string $codigo, string $desc): OidcError
{
    return new OidcError($codigo, $desc, true, 400, $pedido);
}

/** URL de vuelta a la app con un error. */
function oidc_url_error(array $pedido, string $error, string $desc): string
{
    $p = ['error' => $error, 'error_description' => $desc, 'iss' => oidc_issuer()];
    if ($pedido['state'] !== null) {
        $p['state'] = $pedido['state'];
    }
    return oidc_agregar_params($pedido['redirect_uri'], $p);
}

function oidc_agregar_params(string $uri, array $p): string
{
    return $uri . (str_contains($uri, '?') ? '&' : '?') . http_build_query($p);
}

/**
 * Emite el código de autorización y devuelve la URL de vuelta a la app.
 * Anota la entrada a la app en la sesión (para avisarle el cierre) y en la auditoría.
 */
function oidc_emitir_codigo(array $pedido, array $usuario, string $sid, string $rol): string
{
    $app = $pedido['app'];
    $codigo = b64url_enc(random_bytes(32));
    $t = ahora();
    qx('INSERT INTO oidc_codigos (codigo_hash, aplicacion_id, usuario_id, sesion_id, redirect_uri, scope, nonce,
                                  code_challenge, creado_en, expira_en)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        hash('sha256', $codigo), $app['id'], (int)$usuario['id'], $sid, $pedido['redirect_uri'], $pedido['scope'],
        $pedido['nonce'], $pedido['code_challenge'], $t, ahora((int)cfg('oidc.codigo_ttl', 60)),
    ]);

    $n = qx('UPDATE sesion_apps SET ultimo_ingreso = ?, rol = ? WHERE sesion_id = ? AND aplicacion_id = ?',
        [$t, $rol, $sid, $app['id']]);
    if ($n === 0) {
        qx('INSERT INTO sesion_apps (sesion_id, aplicacion_id, rol, primer_ingreso, ultimo_ingreso) VALUES (?, ?, ?, ?, ?)',
            [$sid, $app['id'], $rol, $t, $t]);
    }
    auditar((int)$usuario['id'], 'app_ingreso', $app['id'], $app['clave'], ['rol' => $rol]);

    // Limpieza de lo vencido hace más de una hora (barata y ocasional).
    if (random_int(1, 20) === 1) {
        qx('DELETE FROM oidc_codigos WHERE expira_en < ?', [ahora(-3600)]);
        qx('DELETE FROM oidc_tokens WHERE expira_en < ?', [ahora(-3600)]);
    }

    $p = ['code' => $codigo, 'iss' => oidc_issuer()];
    if ($pedido['state'] !== null) {
        $p['state'] = $pedido['state'];
    }
    return oidc_agregar_params($pedido['redirect_uri'], $p);
}

/**
 * Identifica a la app que llama a /oauth/token: HTTP Basic o client_id +
 * client_secret en el cuerpo (no las dos cosas).
 *
 * @throws OidcError invalid_client (401)
 */
function oidc_autenticar_cliente(array $server, array $post): array
{
    $id = $secreto = null;
    $auth = (string)($server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'Basic ') === 0) {
        $dec = base64_decode(substr($auth, 6), true);
        if ($dec !== false && str_contains($dec, ':')) {
            [$id, $secreto] = explode(':', $dec, 2);
            // RFC 6749 2.3.1: van codificados como formulario.
            $id = urldecode($id);
            $secreto = urldecode($secreto);
        }
    } elseif (isset($server['PHP_AUTH_USER'])) {
        $id = (string)$server['PHP_AUTH_USER'];
        $secreto = (string)($server['PHP_AUTH_PW'] ?? '');
    }
    if ($id !== null && isset($post['client_secret'])) {
        throw new OidcError('invalid_request', 'Credenciales enviadas de dos formas a la vez.');
    }
    if ($id === null) {
        $id = isset($post['client_id']) ? (string)$post['client_id'] : null;
        $secreto = isset($post['client_secret']) ? (string)$post['client_secret'] : null;
    }
    $app = ($id !== null && $id !== '') ? app_por_clave($id) : null;
    if ($app === null || empty($app['secreto_hash']) || $secreto === null
        || !hash_equals((string)$app['secreto_hash'], hash('sha256', $secreto))) {
        throw new OidcError('invalid_client', 'Credenciales de la aplicación inválidas.', false, 401);
    }
    return $app;
}

/**
 * Canjea un código por tokens.
 *
 * @return array respuesta JSON de /oauth/token
 * @throws OidcError
 */
function oidc_canjear_codigo(array $app, array $post): array
{
    if (($post['grant_type'] ?? '') !== 'authorization_code') {
        throw new OidcError('unsupported_grant_type', 'Sólo se admite authorization_code.');
    }
    $codigo = (string)($post['code'] ?? '');
    $f = $codigo !== '' ? q1('SELECT * FROM oidc_codigos WHERE codigo_hash = ?', [hash('sha256', $codigo)]) : null;
    if ($f === null || (int)$f['aplicacion_id'] !== $app['id']) {
        throw new OidcError('invalid_grant', 'Código inválido.');
    }
    // Consumo atómico: si dos pedidos llegan juntos, sólo uno gana.
    if (qx('UPDATE oidc_codigos SET usado_en = ? WHERE codigo_hash = ? AND usado_en IS NULL', [ahora(), $f['codigo_hash']]) === 0) {
        qx('DELETE FROM oidc_tokens WHERE sesion_id = ? AND aplicacion_id = ?', [$f['sesion_id'], $app['id']]);
        throw new OidcError('invalid_grant', 'El código ya se usó.');
    }
    if ($f['expira_en'] < ahora()) {
        throw new OidcError('invalid_grant', 'El código venció.');
    }
    if ((string)($post['redirect_uri'] ?? '') !== $f['redirect_uri']) {
        throw new OidcError('invalid_grant', 'redirect_uri no coincide con la del pedido.');
    }
    if ($f['code_challenge'] !== null) {
        $ver = (string)($post['code_verifier'] ?? '');
        if ($ver === '' || !hash_equals($f['code_challenge'], b64url_enc(hash('sha256', $ver, true)))) {
            throw new OidcError('invalid_grant', 'code_verifier inválido.');
        }
    }
    [$usuario, $sesion] = oidc_usuario_de_sesion($f['sesion_id'], (int)$f['usuario_id']);
    $rol = rol_en_app($usuario, $app);
    if ($rol === null) {
        throw new OidcError('invalid_grant', 'El usuario ya no tiene acceso a esta aplicación.');
    }

    $access = b64url_enc(random_bytes(32));
    $ttl = (int)cfg('oidc.access_ttl', 3600);
    qx('INSERT INTO oidc_tokens (token_hash, aplicacion_id, usuario_id, sesion_id, scope, creado_en, expira_en)
        VALUES (?, ?, ?, ?, ?, ?, ?)',
        [hash('sha256', $access), $app['id'], $usuario['id'], $f['sesion_id'], $f['scope'], ahora(), ahora($ttl)]);

    return [
        'access_token' => $access,
        'token_type'   => 'Bearer',
        'expires_in'   => $ttl,
        'scope'        => $f['scope'],
        'id_token'     => oidc_id_token($app, $usuario, $f['sesion_id'], $f['nonce'], $rol, $f['scope'],
                                        (int)strtotime($sesion['creada_en'])),
    ];
}

/**
 * Usuario y sesión, sólo si la sesión sigue abierta y el usuario activo.
 *
 * @return array{0:array,1:array}
 * @throws OidcError invalid_grant
 */
function oidc_usuario_de_sesion(string $sid, int $usuarioId): array
{
    $s = q1('SELECT * FROM sesiones WHERE id = ? AND usuario_id = ?', [$sid, $usuarioId]);
    $u = q1('SELECT * FROM usuarios WHERE id = ?', [$usuarioId]);
    if ($s === null || $s['cerrada_en'] !== null || $u === null || (int)$u['activo'] !== 1) {
        throw new OidcError('invalid_grant', 'La sesión de ITHub ya no está activa.');
    }
    $u['id'] = (int)$u['id'];
    return [$u, $s];
}

/** Datos de la persona según los scopes pedidos, más su rol en la app. */
function oidc_claims_usuario(array $u, string $rol, string $scope): array
{
    $sc = explode(' ', $scope);
    $c = ['sub' => (string)$u['id']];
    if (in_array('email', $sc, true)) {
        $c['email'] = $u['email'];
        $c['email_verified'] = true;
    }
    if (in_array('profile', $sc, true)) {
        $c['name'] = $u['nombre'];
        $partes = preg_split('/\s+/', trim($u['nombre']), 2) ?: [];
        $c['given_name'] = $partes[0] ?? '';
        $c['family_name'] = $partes[1] ?? '';
        if (!empty($u['avatar_url'])) {
            $c['picture'] = $u['avatar_url'];
        }
    }
    if ($dom = (string)cfg('google.allowed_domain', '')) {
        $c['hd'] = $dom;
    }
    $c['rol'] = $rol;
    $c['roles'] = [$rol];
    $c['rol_portal'] = $u['rol'];
    return $c;
}

/** id_token firmado para la app. */
function oidc_id_token(array $app, array $u, string $sid, ?string $nonce, string $rol, string $scope, int $authTime): string
{
    $t = time();
    $claims = [
        'iss'       => oidc_issuer(),
        'aud'       => $app['clave'],
        'iat'       => $t,
        'exp'       => $t + (int)cfg('oidc.id_token_ttl', 300),
        'auth_time' => $authTime,
        'sid'       => $sid,
    ] + oidc_claims_usuario($u, $rol, $scope);
    if ($nonce !== null) {
        $claims['nonce'] = $nonce;
    }
    $k = oidc_claves();
    return jwt_firmar($claims, $k['privada'], $k['jwk']['kid']);
}

/**
 * Respuesta de /oauth/userinfo para un access token.
 *
 * @throws OidcError invalid_token (401)
 */
function oidc_userinfo(string $token): array
{
    $f = $token !== '' ? q1('SELECT * FROM oidc_tokens WHERE token_hash = ?', [hash('sha256', $token)]) : null;
    if ($f === null || $f['expira_en'] < ahora()) {
        throw new OidcError('invalid_token', 'Token inválido o vencido.', false, 401);
    }
    try {
        [$u] = oidc_usuario_de_sesion($f['sesion_id'], (int)$f['usuario_id']);
    } catch (OidcError) {
        throw new OidcError('invalid_token', 'La sesión de ITHub ya no está activa.', false, 401);
    }
    $app = app_por_id((int)$f['aplicacion_id']);
    $rol = $app ? rol_en_app($u, $app) : null;
    if ($rol === null) {
        throw new OidcError('invalid_token', 'El usuario ya no tiene acceso a esta aplicación.', false, 401);
    }
    return oidc_claims_usuario($u, $rol, $f['scope']);
}

/** logout_token (OpenID Connect Back-Channel Logout 1.0) para una app. */
function oidc_logout_token(array $app, int $usuarioId, string $sid): string
{
    $t = time();
    $k = oidc_claves();
    return jwt_firmar([
        'iss'    => oidc_issuer(),
        'aud'    => $app['clave'],
        'iat'    => $t,
        'exp'    => $t + 120,
        'jti'    => aleatorio_hex(16),
        'sub'    => (string)$usuarioId,
        'sid'    => $sid,
        'events' => [OIDC_EVENTO_LOGOUT => new stdClass()],
    ], $k['privada'], $k['jwk']['kid']);
}

/**
 * Avisa el cierre de una sesión a las apps a las que se entró con ella (o
 * sólo a $soloAppId). Mejor esfuerzo: una app caída no frena el cierre; queda
 * en el log y la app igual corta en su propio vencimiento.
 *
 * @return array<string,int> clave de app → código HTTP (0 = sin respuesta)
 */
function oidc_avisar_cierre(string $sid, ?int $soloAppId = null): array
{
    $filas = q('SELECT sa.aplicacion_id, s.usuario_id FROM sesion_apps sa JOIN sesiones s ON s.id = sa.sesion_id
                WHERE sa.sesion_id = ?', [$sid]);
    $res = [];
    foreach ($filas as $f) {
        if ($soloAppId !== null && (int)$f['aplicacion_id'] !== $soloAppId) {
            continue;
        }
        $app = app_por_id((int)$f['aplicacion_id']);
        if ($app === null || empty($app['backchannel_uri'])) {
            continue;
        }
        try {
            [$codigo] = http_post($app['backchannel_uri'],
                ['logout_token' => oidc_logout_token($app, (int)$f['usuario_id'], $sid)], 5);
        } catch (Throwable $e) {
            $codigo = 0;
        }
        if ($codigo < 200 || $codigo >= 300) {
            error_log("[ithub] aviso de cierre a {$app['clave']} sin éxito (HTTP $codigo), sesión $sid");
        }
        $res[$app['clave']] = $codigo;
    }
    return $res;
}

/**
 * Le quita a un usuario el acceso a una app en las sesiones abiertas: avisa
 * el cierre a esa app y borra sus access tokens. Se usa al quitar un permiso.
 */
function oidc_revocar_app_usuario(int $usuarioId, int $appId): void
{
    $sesiones = q('SELECT sa.sesion_id FROM sesion_apps sa JOIN sesiones s ON s.id = sa.sesion_id
                   WHERE s.usuario_id = ? AND s.cerrada_en IS NULL AND sa.aplicacion_id = ?', [$usuarioId, $appId]);
    foreach ($sesiones as $s) {
        oidc_avisar_cierre($s['sesion_id'], $appId);
        qx('DELETE FROM sesion_apps WHERE sesion_id = ? AND aplicacion_id = ?', [$s['sesion_id'], $appId]);
    }
    qx('DELETE FROM oidc_tokens WHERE usuario_id = ? AND aplicacion_id = ?', [$usuarioId, $appId]);
}

/**
 * Genera un secreto nuevo para la app (invalida el anterior). Devuelve el
 * secreto en claro: es la única vez que se ve.
 */
function oidc_generar_secreto(int $appId): string
{
    $secreto = 'ith_' . b64url_enc(random_bytes(32));
    qx('UPDATE aplicaciones SET secreto_hash = ?, actualizado_en = ? WHERE id = ?',
        [hash('sha256', $secreto), ahora(), $appId]);
    return $secreto;
}

/**
 * Valida un id_token_hint de /oauth/logout: tiene que estar firmado por
 * ITHub (puede estar vencido: es lo normal al salir). Devuelve sus claims o null.
 */
function oidc_validar_hint(string $hint): ?array
{
    $c = jwt_verificar($hint, oidc_claves()['publica']);
    return ($c !== null && ($c['iss'] ?? '') === oidc_issuer()) ? $c : null;
}
