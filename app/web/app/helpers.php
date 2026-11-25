<?php
declare(strict_types=1);

// ============================================================
//  Utilidades generales: escape, URLs, redirecciones, CSRF, avisos,
//  fechas, IP del visitante, JSON y vistas.
// ============================================================

/** Escape para HTML. Todo dato que sale a una vista pasa por acá. */
function e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Configuración (con puntos: cfg('app.url')). */
function cfg(string $clave, mixed $defecto = null): mixed
{
    $v = $GLOBALS['CONFIG'] ?? [];
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($v) || !array_key_exists($parte, $v)) {
            return $defecto;
        }
        $v = $v[$parte];
    }
    return $v;
}

/** URL interna relativa: url('usuarios', ['q' => 'ana']) → /usuarios?q=ana */
function url(string $ruta = '', array $params = []): string
{
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    $u = $base . '/' . ltrim($ruta, '/');
    return $params ? $u . '?' . http_build_query($params) : $u;
}

/** URL absoluta canónica (la de app.url). La usan Google y OpenID Connect. */
function url_abs(string $ruta = '', array $params = []): string
{
    $u = rtrim((string)cfg('app.url', ''), '/') . '/' . ltrim($ruta, '/');
    return $params ? $u . '?' . http_build_query($params) : $u;
}

/** Corta el pedido con una redirección. */
function redirigir(string $url, int $codigo = 302): never
{
    header('Location: ' . $url, true, $codigo);
    exit;
}

/** Fecha y hora actual en el formato de la base. */
function ahora(int $desplazamiento = 0): string
{
    return date('Y-m-d H:i:s', time() + $desplazamiento);
}

/** "25/11/2026 14:30" o "—". */
function fecha_legible(?string $f, bool $conHora = true): string
{
    if (!$f) {
        return '—';
    }
    $t = strtotime($f);
    return $t ? date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $t) : '—';
}

/** "hace 5 min", "hace 2 h", "ayer"... para listados. */
function hace(?string $f): string
{
    if (!$f) {
        return 'nunca';
    }
    $s = time() - (int)strtotime($f);
    return match (true) {
        $s < 60     => 'recién',
        $s < 3600   => 'hace ' . intdiv($s, 60) . ' min',
        $s < 86400  => 'hace ' . intdiv($s, 3600) . ' h',
        $s < 172800 => 'ayer',
        default     => fecha_legible($f, false),
    };
}

/** Hex aleatorio criptográfico de $bytes bytes. */
function aleatorio_hex(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/** ¿El pedido llegó por HTTPS (directo o detrás del CDN de Hostinger)? */
function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/**
 * IP del visitante. X-Forwarded-For sólo se cree si el pedido llega desde un
 * proxy propio declarado en app.proxies_confiables; si no, cualquiera podría
 * escribir la IP que quisiera en la auditoría.
 */
function ip_cliente(): string
{
    $remota = $_SERVER['REMOTE_ADDR'] ?? '';
    $proxies = (array)cfg('app.proxies_confiables', []);
    if ($proxies && in_array($remota, $proxies, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $primera = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($primera, FILTER_VALIDATE_IP)) {
            return $primera;
        }
    }
    return $remota;
}

/** Responde JSON y corta. */
function json_salida(array $datos, int $estado = 200, array $cabeceras = []): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    foreach ($cabeceras as $k => $v) {
        header($k . ': ' . $v);
    }
    echo json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- CSRF ----

/** Token CSRF de la sesión (se crea la primera vez). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = aleatorio_hex(32);
    }
    return $_SESSION['csrf'];
}

/** Campo oculto para los formularios. */
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Corta con 400 si el POST no trae el token correcto. */
function csrf_verificar(): void
{
    $enviado = (string)($_POST['_csrf'] ?? '');
    if ($enviado === '' || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        vista('error', [
            'titulo'  => 'El formulario venció',
            'mensaje' => 'Volvé a la pantalla anterior, recargala y probá de nuevo.',
        ]);
        exit;
    }
}

/** Exige método POST. */
function requerir_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
    csrf_verificar();
}

// ---- Avisos de una sola vez (flash) ----

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** @return array<int,array{tipo:string,mensaje:string}> */
function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---- Vistas ----

/**
 * Muestra una vista dentro del layout. Las variables de $datos quedan
 * disponibles en la vista con su nombre. Una vista que no quiere el layout
 * (por ejemplo el login) pasa 'layout' => 'auth'.
 */
function vista(string $nombre, array $datos = []): void
{
    $datos['__vista'] = $nombre;
    extract($datos, EXTR_SKIP);
    $layout = $datos['layout'] ?? 'app';
    ob_start();
    require APP . '/views/' . $nombre . '.php';
    $contenido = ob_get_clean();
    require APP . '/views/layout.php';
}

/** Iniciales para el avatar sin foto: "Brian Sierra" → "BS". */
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre)) ?: [];
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini !== '' ? $ini : '?';
}

/** Primer nombre para el saludo. */
function primer_nombre(string $nombre): string
{
    $p = preg_split('/\s+/', trim($nombre)) ?: [];
    return $p[0] ?? '';
}

/** Navegador y sistema en pocas palabras, para el listado de sesiones. */
function navegador_corto(?string $ua): string
{
    $ua = (string)$ua;
    $nav = match (true) {
        str_contains($ua, 'Edg/')     => 'Edge',
        str_contains($ua, 'OPR/')     => 'Opera',
        str_contains($ua, 'Chrome/')  => 'Chrome',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Safari/')  => 'Safari',
        default                       => 'Navegador',
    };
    $so = match (true) {
        str_contains($ua, 'Windows')   => 'Windows',
        str_contains($ua, 'Android')   => 'Android',
        str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Mac OS')    => 'macOS',
        str_contains($ua, 'Linux')     => 'Linux',
        default                        => '',
    };
    return $so !== '' ? "$nav en $so" : $nav;
}
