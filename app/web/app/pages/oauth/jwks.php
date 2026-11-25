<?php
declare(strict_types=1);

// /oauth/jwks — clave pública con la que las apps verifican los id_token.
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode(['keys' => [oidc_claves()['jwk']]], JSON_UNESCAPED_SLASHES);
