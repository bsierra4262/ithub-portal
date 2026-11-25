<?php
declare(strict_types=1);

// ============================================================
//  /oauth/token — la app (desde su servidor) canjea el código por el
//  id_token. Sin sesión ni cookies: se autentica con su secreto.
// ============================================================

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_salida(['error' => 'invalid_request', 'error_description' => 'Usar POST.'], 405, ['Allow' => 'POST']);
}

try {
    $app = oidc_autenticar_cliente($_SERVER, $_POST);
    json_salida(oidc_canjear_codigo($app, $_POST));
} catch (OidcError $e) {
    $cab = $e->http === 401 ? ['WWW-Authenticate' => 'Basic realm="ITHub"'] : [];
    json_salida(['error' => $e->codigo, 'error_description' => $e->getMessage()], $e->http, $cab);
}
