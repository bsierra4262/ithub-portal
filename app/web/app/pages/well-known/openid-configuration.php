<?php
declare(strict_types=1);

// /.well-known/openid-configuration — todo lo que una app necesita saber para
// conectarse (las librerías cliente lo leen solas a partir del issuer).
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode(oidc_discovery(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
