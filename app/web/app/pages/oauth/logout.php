<?php
declare(strict_types=1);

// ============================================================
//  /oauth/logout — una app pide cerrar la sesión (RP-Initiated Logout).
//
//  Como la sesión es una sola, salir desde cualquier app es salir de todas.
//  Si el pedido trae un id_token_hint firmado por ITHub, se cierra directo; si
//  no lo trae, se pide confirmación: un enlace cualquiera de otro sitio no
//  puede sacar a nadie.
//
//  Adónde se vuelve: SIEMPRE al login de ITHub con el aviso «Cerraste sesión
//  en ITHub y en todas las aplicaciones», aunque la app mande una
//  post_logout_redirect_uri. Volver al login de la app la reenviaba en el acto
//  a ITHub con «Para entrar a <app>…» y, al ingresar, a esa app en vez del
//  panel con todas (pedido de Brian, 25/11). La uri sólo se usa para saber
//  desde qué app se salió.
// ============================================================

$hint = (string)($_REQUEST['id_token_hint'] ?? '');
$claims = $hint !== '' ? oidc_validar_hint($hint) : null;
$clientId = (string)($claims['aud'] ?? ($_REQUEST['client_id'] ?? ''));
$app = $clientId !== '' ? app_por_clave($clientId) : null;

$confirmado = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && hash_equals(csrf_token(), (string)($_POST['_csrf'] ?? ''));
$hintValido = $claims !== null && $yo !== null && (string)($claims['sub'] ?? '') === (string)$yo['id'];

if ($yo === null) {
    redirigir(url('login', ['salio' => 1]));
}

if ($hintValido || $confirmado) {
    cerrar_sesion($yo['sid'], $app ? 'salida_app' : 'salida', $yo['id']);
    auditar($yo['id'], 'salida', $app['id'] ?? null, $yo['email'], $app ? ['desde' => $app['clave']] : []);
    salir_local();
    redirigir(url('login', ['salio' => 1]));
}

vista('logout-confirmar', [
    'layout' => 'auth',
    'titulo' => 'Cerrar sesión',
    'yo'     => $yo,
    'app'    => $app,
    'campos' => array_filter([
        'client_id'                => $clientId,
        'post_logout_redirect_uri' => (string)($_REQUEST['post_logout_redirect_uri'] ?? ''),
        'state'                    => isset($_REQUEST['state']) ? (string)$_REQUEST['state'] : '',
    ], static fn(string $v): bool => $v !== ''),
]);
