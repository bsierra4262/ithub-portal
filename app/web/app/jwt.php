<?php
declare(strict_types=1);

// ============================================================
//  JWT firmados con RS256 (los id_token y los logout_token de OpenID
//  Connect) y la clave pública publicada como JWK.
//
//  Sólo RS256: es lo que acepta cualquier librería cliente y lo que
//  anunciamos en el discovery. Un token con otro `alg` (incluido "none") no
//  se verifica nunca.
// ============================================================

function b64url_enc(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_dec(string $txt): string|false
{
    $txt = strtr($txt, '-_', '+/');
    $pad = strlen($txt) % 4;
    if ($pad) {
        $txt .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($txt, true);
}

/** Firma $claims con la clave privada PEM. */
function jwt_firmar(array $claims, string $privadaPem, string $kid): string
{
    $cab = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid];
    $parte = b64url_enc(json_encode($cab, JSON_UNESCAPED_SLASHES)) . '.'
           . b64url_enc(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $firma = '';
    if (!openssl_sign($parte, $firma, $privadaPem, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('No se pudo firmar el token.');
    }
    return $parte . '.' . b64url_enc($firma);
}

/**
 * Separa un JWT en [cabecera, claims, firma, parte firmada] SIN verificar.
 * null si no tiene forma de JWT.
 */
function jwt_partes(string $jwt): ?array
{
    $p = explode('.', $jwt);
    if (count($p) !== 3) {
        return null;
    }
    $cab = json_decode((string)b64url_dec($p[0]), true);
    $claims = json_decode((string)b64url_dec($p[1]), true);
    $firma = b64url_dec($p[2]);
    if (!is_array($cab) || !is_array($claims) || $firma === false) {
        return null;
    }
    return [$cab, $claims, $firma, $p[0] . '.' . $p[1]];
}

/** Verifica la firma RS256 y devuelve los claims, o null. No mira fechas. */
function jwt_verificar(string $jwt, string $publicaPem): ?array
{
    $p = jwt_partes($jwt);
    if ($p === null || ($p[0]['alg'] ?? '') !== 'RS256') {
        return null;
    }
    return openssl_verify($p[3], $p[2], $publicaPem, OPENSSL_ALGO_SHA256) === 1 ? $p[1] : null;
}

/** Clave pública PEM y su JWK a partir de la privada. */
function jwk_de_privada(string $privadaPem): array
{
    $k = openssl_pkey_get_private($privadaPem);
    if ($k === false) {
        throw new RuntimeException('La clave privada de OpenID Connect no se puede leer.');
    }
    $d = openssl_pkey_get_details($k);
    if (($d['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
        throw new RuntimeException('La clave de OpenID Connect tiene que ser RSA.');
    }
    $n = b64url_enc($d['rsa']['n']);
    $e = b64url_enc($d['rsa']['e']);
    // kid: huella de la clave pública (RFC 7638), estable mientras no se rote.
    $huella = json_encode(['e' => $e, 'kty' => 'RSA', 'n' => $n], JSON_UNESCAPED_SLASHES);
    $kid = substr(b64url_enc(hash('sha256', $huella, true)), 0, 16);
    return [
        'publica' => $d['key'],
        'jwk'     => ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid, 'n' => $n, 'e' => $e],
    ];
}

/** Genera un par RSA de 2048 bits y devuelve la privada en PEM. */
function generar_clave_rsa(): string
{
    $opc = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    // En Linux OpenSSL encuentra solo su configuración. El PHP de Windows la
    // trae en extras/ssl y sin ella openssl_pkey_new() devuelve false.
    $cnf = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
    if (!getenv('OPENSSL_CONF') && is_file($cnf)) {
        $opc['config'] = $cnf;
    }
    $k = openssl_pkey_new($opc);
    if ($k === false) {
        throw new RuntimeException('OpenSSL no pudo generar la clave RSA: ' . (string)openssl_error_string());
    }
    $pem = '';
    openssl_pkey_export($k, $pem, null, $opc);
    return $pem;
}
