# Conectar una aplicación a ITHub

Guía para que una app de Intellihelp deje de tener su propio login y entre con la sesión
única de ITHub. ITHub es un proveedor **OpenID Connect** estándar (flujo *Authorization
Code*), así que cualquier librería cliente de OIDC sirve; abajo está además el código mínimo
en PHP plano, que es lo que usan las apps de la casa.

## Qué cambia para la app

| Antes | Después |
|---|---|
| La app manda a Google y valida el `hd` | La app manda a ITHub; ITHub ya validó Google y el dominio |
| Cada app decide quién entra | ITHub decide: si la persona no tiene permiso, ni llega a la app |
| El rol vive sólo en la tabla de la app | El rol llega en el `id_token` (claim `rol`) y la app lo aplica |
| Salir de una app no cierra las demás | Salir de cualquier lado cierra en todas (back-channel logout) |

La app **sigue teniendo su tabla de usuarios** (para sus relaciones: quién creó qué); la fila
se crea o se actualiza en el primer ingreso con los datos del `id_token`.

## 1. Alta en ITHub

En **ITHub → Aplicaciones → la app** (o *Nueva aplicación*):

1. **Roles**: los mismos nombres que usa la app (`admin, operador`), el de más jerarquía primero.
2. **URIs de vuelta**: la dirección exacta del callback de la app, p. ej.
   `https://remitos.intellihelp.tech/ithub-callback`. Coincidencia exacta: ni una barra de más.
3. **URIs después de salir**: adónde vuelve la persona después de cerrar sesión (la pantalla de
   ingreso de la app).
4. **Aviso de cierre**: la dirección donde ITHub avisa que la sesión se cerró, p. ej.
   `https://remitos.intellihelp.tech/ithub-logout`.
5. **Generar secreto**: se muestra **una sola vez**. Va a la configuración de la app (fuera
   del repo) y a su repo `-claves`.

## 2. Datos del proveedor

| Dato | Valor |
|---|---|
| Issuer | `https://ithub.intellihelp.tech` |
| Discovery | `https://ithub.intellihelp.tech/.well-known/openid-configuration` |
| Autorización | `https://ithub.intellihelp.tech/oauth/authorize` |
| Token | `https://ithub.intellihelp.tech/oauth/token` |
| Userinfo | `https://ithub.intellihelp.tech/oauth/userinfo` |
| Claves públicas (JWKS) | `https://ithub.intellihelp.tech/oauth/jwks` |
| Cerrar sesión | `https://ithub.intellihelp.tech/oauth/logout` |
| client_id | la clave de la app en ITHub (`remitos`, `crm`…) |
| Autenticación del cliente | `client_secret_basic` o `client_secret_post` |
| Firma | RS256 |
| Scopes | `openid email profile` |
| PKCE | `S256` (recomendado) |

### Claims del `id_token`

```json
{
  "iss": "https://ithub.intellihelp.tech",
  "aud": "remitos",
  "sub": "12",
  "sid": "2e2a30605f0a3615323fea180b81dc05",
  "email": "juan@intellihelp.tech",
  "email_verified": true,
  "name": "Juan Gómez",
  "picture": "https://lh3.googleusercontent.com/...",
  "hd": "intellihelp.tech",
  "rol": "operador",
  "roles": ["operador"],
  "rol_portal": "usuario",
  "nonce": "...", "iat": 0, "exp": 0, "auth_time": 0
}
```

- `sub` es el id estable de la persona en ITHub: conviene guardarlo en la tabla de la app
  (`ithub_sub`) además del email.
- `rol` es **el rol de la persona en ESTA app**. Si no tiene acceso, ITHub no emite el token.
- `sid` identifica la sesión de ITHub: hay que guardarlo en la sesión de la app para poder
  cerrarla cuando llegue el aviso.

## 3. Código mínimo en PHP plano

Configuración de la app (fuera del web root):

```php
'ithub' => [
    'issuer'        => 'https://ithub.intellihelp.tech',
    'client_id'     => 'remitos',
    'client_secret' => 'ith_...',                                   // NUNCA en git
    'redirect_uri'  => 'https://remitos.intellihelp.tech/ithub-callback',
],
```

### 3.1 Ingreso: mandar a ITHub

La pantalla de login de la app redirige sola (ya no tiene botón de Google):

```php
$_SESSION['ithub_state'] = bin2hex(random_bytes(16));
$_SESSION['ithub_nonce'] = bin2hex(random_bytes(16));
$_SESSION['ithub_ver']   = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
$challenge = rtrim(strtr(base64_encode(hash('sha256', $_SESSION['ithub_ver'], true)), '+/', '-_'), '=');

header('Location: ' . $cfg['issuer'] . '/oauth/authorize?' . http_build_query([
    'client_id'             => $cfg['client_id'],
    'redirect_uri'          => $cfg['redirect_uri'],
    'response_type'         => 'code',
    'scope'                 => 'openid email profile',
    'state'                 => $_SESSION['ithub_state'],
    'nonce'                 => $_SESSION['ithub_nonce'],
    'code_challenge'        => $challenge,
    'code_challenge_method' => 'S256',
]));
exit;
```

### 3.2 Callback: canjear el código y validar el token

```php
if (!hash_equals($_SESSION['ithub_state'] ?? '', $_GET['state'] ?? '')) { exit('state inválido'); }
if (isset($_GET['error'])) { /* access_denied: la persona no tiene permiso */ }

$ch = curl_init($cfg['issuer'] . '/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
    CURLOPT_USERPWD => $cfg['client_id'] . ':' . $cfg['client_secret'],
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'    => 'authorization_code',
        'code'          => $_GET['code'],
        'redirect_uri'  => $cfg['redirect_uri'],
        'code_verifier' => $_SESSION['ithub_ver'],
    ]),
]);
$r = json_decode((string)curl_exec($ch), true);
$claims = ithub_validar_id_token($r['id_token'] ?? '', $cfg, $_SESSION['ithub_nonce']);
unset($_SESSION['ithub_state'], $_SESSION['ithub_nonce'], $_SESSION['ithub_ver']);

// Buscar o crear el usuario local por `sub` (o email) y aplicar `rol`.
$usuario = usuario_local_desde_ithub($claims);
session_regenerate_id(true);
$_SESSION['uid'] = $usuario['id'];
$_SESSION['ithub_sid'] = $claims['sid'];       // para el aviso de cierre
$_SESSION['ithub_id_token'] = $r['id_token'];  // para salir (id_token_hint)
```

Validación del `id_token` (firma RS256 con la clave de `/oauth/jwks` y claims):

```php
function ithub_validar_id_token(string $jwt, array $cfg, ?string $nonce): array
{
    [$h, $p, $s] = explode('.', $jwt) + [null, null, null];
    $dec = fn(string $x) => base64_decode(strtr($x, '-_', '+/') . str_repeat('=', (4 - strlen($x) % 4) % 4));
    $cab = json_decode($dec((string)$h), true);
    $c = json_decode($dec((string)$p), true);
    if (($cab['alg'] ?? '') !== 'RS256') { throw new RuntimeException('alg'); }
    if (openssl_verify("$h.$p", $dec((string)$s), ithub_clave_publica($cfg, $cab['kid'] ?? ''), OPENSSL_ALGO_SHA256) !== 1) {
        throw new RuntimeException('firma');
    }
    if (($c['iss'] ?? '') !== $cfg['issuer'] || ($c['aud'] ?? '') !== $cfg['client_id']) { throw new RuntimeException('iss/aud'); }
    if (($c['exp'] ?? 0) < time() - 60) { throw new RuntimeException('vencido'); }
    if ($nonce !== null && !hash_equals($nonce, (string)($c['nonce'] ?? ''))) { throw new RuntimeException('nonce'); }
    return $c;
}
```

`ithub_clave_publica()` baja `/oauth/jwks`, la guarda en caché (un archivo, 1 h) y arma el PEM
a partir de `n` y `e`. La forma más corta es con `phpseclib` (`PublicKeyLoader::load(['e' =>
..., 'n' => ...])`); sin dependencias, armando el DER de la clave a mano (unas 20 líneas). Si el
`kid` del token no está en la caché, se vuelve a bajar (rotación de clave).

### 3.3 Aviso de cierre (back-channel logout)

ITHub hace un `POST` con `logout_token` (un JWT firmado) cuando la sesión se cierra. La app lo
valida igual que el `id_token` (sin `nonce`; tiene que traer `events` con
`http://schemas.openid.net/event/backchannel-logout`) y cierra **toda sesión local con ese
`sid`**. Como el aviso no viene del navegador, no hay cookie: la app tiene que poder encontrar
la sesión por `sid` (una columna `ithub_sid` en su tabla de sesiones, o un archivo/tabla
`sid → sesiones a invalidar` que el control de sesión consulta en cada pedido).

Responder `200` si se procesó, `400` si el token no es válido.

### 3.4 Salir

El botón *Cerrar sesión* de la app cierra su sesión local y manda a:

```
https://ithub.intellihelp.tech/oauth/logout?id_token_hint=<id_token>&post_logout_redirect_uri=<uri registrada>
```

Con `id_token_hint` ITHub cierra sin preguntar y vuelve a la app. Como la sesión es una sola,
se cierra en **todas** las apps.

## 4. Apps de terceros

- **Outline (Wiki)**: trae OIDC de fábrica. Variables `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`,
  `OIDC_AUTH_URI`, `OIDC_TOKEN_URI`, `OIDC_USERINFO_URI`, `OIDC_LOGOUT_URI`,
  `OIDC_USERNAME_CLAIM=email`, `OIDC_DISPLAY_NAME=ITHub`, `OIDC_SCOPES="openid email profile"`.
  Callback: `https://wiki.intellihelp.tech/auth/oidc.callback`.
- **GLPI (Mesa de Ayuda)**: con el plugin de SSO por OAuth/OIDC; se mapea el email al usuario
  de GLPI y el claim `rol` a su perfil.

## 5. Checklist antes de dar por conectada una app

- [ ] Sin permiso en ITHub, la persona no entra (pantalla «No tenés acceso»).
- [ ] Con permiso, entra sin ver ninguna pantalla de login.
- [ ] Cambiar el rol en ITHub cambia lo que ve en la app al volver a entrar.
- [ ] Quitarle la app en ITHub la saca de la app (aviso de cierre).
- [ ] *Cerrar sesión* en la app la saca de ITHub y de las demás apps.
- [ ] El secreto está sólo en la configuración del servidor y en el repo `-claves`.
