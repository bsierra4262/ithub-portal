<?php
declare(strict_types=1);

// ============================================================
//  Google Workspace — OAuth 2.0 (Authorization Code + PKCE, server-side).
//
//  Es la ÚNICA forma de entrar a ITHub. Flujo:
//    pages/google-login.php    arma state, nonce y verificador PKCE (en la
//                              sesión) y manda a google_url_autorizacion();
//    pages/google-callback.php valida el state, canjea el código
//                              (google_canjear_codigo()), valida la identidad
//                              (google_validar_identidad()) y abre la sesión.
//
//  Validación de la identidad: el id_token llega directo de Google por TLS
//  en la respuesta del canje (no pasa por el navegador), así que alcanza con
//  validar sus claims (OpenID Connect Core 3.1.3.7, punto 6): emisor,
//  audiencia, vencimiento, nonce, email verificado y, sobre todo, `hd`.
//  El `hd` de la URL de Google sólo filtra el selector de cuentas y se puede
//  saltear editando la dirección: el control real es el del id_token.
// ============================================================

/** ¿Está configurado el cliente OAuth? */
function google_habilitado(): bool
{
    return (string)cfg('google.client_id', '') !== '' && (string)cfg('google.client_secret', '') !== '';
}

/** URI de vuelta registrada en la consola de Google. Exacta, siempre HTTPS. */
function google_redirect_uri(): string
{
    return url_abs('google-callback');
}

/**
 * URL de Google para elegir la cuenta.
 *
 * @param string $state     anti-CSRF, vuelve en el callback
 * @param string $nonce     vuelve dentro del id_token
 * @param string $verificador verificador PKCE (se manda su challenge S256)
 */
function google_url_autorizacion(string $state, string $nonce, string $verificador): string
{
    $params = [
        'client_id'             => cfg('google.client_id'),
        'redirect_uri'          => google_redirect_uri(),
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'state'                 => $state,
        'nonce'                 => $nonce,
        'code_challenge'        => b64url_enc(hash('sha256', $verificador, true)),
        'code_challenge_method' => 'S256',
        'prompt'                => 'select_account',
    ];
    if ($dom = (string)cfg('google.allowed_domain', '')) {
        $params['hd'] = $dom;
    }
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Canjea el código por tokens. Devuelve el id_token o tira RuntimeException
 * con un mensaje para el log (no para la pantalla).
 */
function google_canjear_codigo(string $codigo, string $verificador): string
{
    [$estado, $cuerpo] = http_post('https://oauth2.googleapis.com/token', [
        'code'          => $codigo,
        'client_id'     => (string)cfg('google.client_id'),
        'client_secret' => (string)cfg('google.client_secret'),
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
        'code_verifier' => $verificador,
    ]);
    $r = json_decode($cuerpo, true);
    if ($estado !== 200 || !is_array($r) || empty($r['id_token'])) {
        throw new RuntimeException('Google rechazó el canje del código (HTTP ' . $estado . '): '
            . (is_array($r) ? ($r['error'] ?? '') . ' ' . ($r['error_description'] ?? '') : 'sin respuesta'));
    }
    return (string)$r['id_token'];
}

/**
 * Valida los claims del id_token de Google y devuelve la identidad.
 *
 * @return array{email:string,nombre:string,avatar:?string}
 * @throws RuntimeException con el motivo (va al log y a la auditoría)
 */
function google_validar_identidad(string $idToken, string $nonceEsperado): array
{
    $p = jwt_partes($idToken);
    if ($p === null) {
        throw new RuntimeException('id_token de Google mal formado');
    }
    $c = $p[1];
    $dominio = mb_strtolower((string)cfg('google.allowed_domain', ''));

    if (!in_array($c['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) {
        throw new RuntimeException('emisor inválido');
    }
    $aud = $c['aud'] ?? null;
    if (!(is_string($aud) && $aud === cfg('google.client_id'))
        && !(is_array($aud) && in_array(cfg('google.client_id'), $aud, true))) {
        throw new RuntimeException('audiencia inválida');
    }
    if ((int)($c['exp'] ?? 0) < time() - 60) {
        throw new RuntimeException('id_token vencido');
    }
    if (!hash_equals($nonceEsperado, (string)($c['nonce'] ?? ''))) {
        throw new RuntimeException('nonce inválido');
    }
    if (($c['email_verified'] ?? false) !== true) {
        throw new RuntimeException('email no verificado');
    }
    $email = mb_strtolower((string)($c['email'] ?? ''));
    if ($dominio !== '') {
        // Las dos cosas: la cuenta es del Workspace (hd) y el email es del
        // dominio. Una cuenta personal de Gmail no trae hd.
        if (mb_strtolower((string)($c['hd'] ?? '')) !== $dominio || !email_del_dominio($email, $dominio)) {
            throw new RuntimeException('cuenta fuera del dominio: ' . $email);
        }
    }
    $nombre = trim((string)($c['name'] ?? ''));
    return [
        'email'  => $email,
        'nombre' => $nombre !== '' ? $nombre : explode('@', $email)[0],
        'avatar' => isset($c['picture']) ? (string)$c['picture'] : null,
    ];
}

/**
 * Busca (o crea, si app.auto_provision) el usuario de ITHub para una
 * identidad de Google ya validada.
 *
 * @return array|null el usuario, o null si no puede entrar (con $motivo)
 */
function google_resolver_usuario(array $id, ?string &$motivo = null): ?array
{
    $u = usuario_por_email($id['email']);
    if ($u === null) {
        if (!cfg('google.auto_provision', true)) {
            $motivo = 'no_registrado';
            return null;
        }
        crear_usuario($id['email'], $id['nombre'], 'usuario', $id['avatar']);
        $u = usuario_por_email($id['email']);
        auditar((int)$u['id'], 'usuario_creado', null, $u['email'], ['origen' => 'primer ingreso con Google']);
    }
    if ((int)$u['activo'] !== 1) {
        $motivo = 'inactivo';
        return null;
    }
    // Nombre y foto se mantienen al día con Google (los cambia la persona allá).
    if ($u['nombre'] !== $id['nombre'] || $u['avatar_url'] !== $id['avatar']) {
        qx('UPDATE usuarios SET nombre = ?, avatar_url = ?, actualizado_en = ? WHERE id = ?',
            [$id['nombre'], $id['avatar'], ahora(), $u['id']]);
        $u['nombre'] = $id['nombre'];
        $u['avatar_url'] = $id['avatar'];
    }
    return $u;
}
