<?php
declare(strict_types=1);

// ============================================================
//  Arranca el ingreso con Google: state, nonce y PKCE en la sesión.
// ============================================================

if (!google_habilitado()) {
    redirigir(url('login'));
}
$_SESSION['g_state'] = aleatorio_hex(16);
$_SESSION['g_nonce'] = aleatorio_hex(16);
$_SESSION['g_verificador'] = b64url_enc(random_bytes(48));
$_SESSION['g_desde'] = time();

redirigir(google_url_autorizacion($_SESSION['g_state'], $_SESSION['g_nonce'], $_SESSION['g_verificador']));
