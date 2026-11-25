<?php
declare(strict_types=1);

// ============================================================
//  Pedidos HTTP salientes (Google y avisos de cierre a las apps).
//  Siempre con verificación de certificado y tiempo máximo corto.
// ============================================================

/**
 * POST de formulario. Devuelve [código HTTP, cuerpo]; código 0 = no hubo
 * respuesta (red, DNS, timeout).
 *
 * @param array<string,string> $campos
 * @param array<int,string>    $cabeceras
 */
function http_post(string $url, array $campos, int $timeout = 10, array $cabeceras = []): array
{
    if (isset($GLOBALS['__http_falso']) && is_callable($GLOBALS['__http_falso'])) {
        return ($GLOBALS['__http_falso'])($url, $campos);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($campos),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/x-www-form-urlencoded'], $cabeceras),
        CURLOPT_USERAGENT      => 'ITHub/1.0',
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$cuerpo === false ? 0 : $codigo, $cuerpo === false ? '' : (string)$cuerpo];
}
