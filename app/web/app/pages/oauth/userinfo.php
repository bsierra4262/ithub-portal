<?php
declare(strict_types=1);

// ============================================================
//  /oauth/userinfo — datos de la persona para un access token (Bearer).
//  Lo usan las apps de terceros (Outline, GLPI) que no leen el id_token.
// ============================================================

$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$token = stripos($auth, 'Bearer ') === 0 ? trim(substr($auth, 7)) : (string)($_POST['access_token'] ?? '');

try {
    json_salida(oidc_userinfo($token));
} catch (OidcError $e) {
    json_salida(['error' => $e->codigo, 'error_description' => $e->getMessage()], $e->http,
        ['WWW-Authenticate' => 'Bearer error="' . $e->codigo . '"']);
}
