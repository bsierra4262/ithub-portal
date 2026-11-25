<?php
declare(strict_types=1);

// ============================================================
//  Instalación y puesta a punto (SÓLO por consola). Idempotente: se puede
//  correr las veces que haga falta, sólo hace lo que falta.
//
//      php app/cli/instalar.php [--schema=/ruta/schema.sql]
//
//    1. Si la base está vacía, importa el esquema (por defecto el del repo,
//       app/db/schema.sql; en el servidor se pasa con --schema).
//    2. Si no existe la clave de firma de OpenID Connect (oidc.clave_privada),
//       la genera con permisos 600.
//    3. Crea como super admin las cuentas de `superadmins_iniciales` que
//       todavía no existan.
//    4. Si no hay aplicaciones, carga el catálogo de cli/catalogo.php.
//
//  No hay contraseñas que mostrar: todos entran con Google.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__) . '/bootstrap.php';

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}

$log = static function (string $m): void { echo "[instalar] $m\n"; };

// 1. Esquema
$tablas = db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
    ? q("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'usuarios'")
    : q("SHOW TABLES LIKE 'usuarios'");
if (!$tablas) {
    // En el servidor, bin/deploy.py deja una copia del esquema al lado de este archivo.
    $schema = is_string($args['schema'] ?? null) ? $args['schema']
        : (is_file(__DIR__ . '/schema.sql') ? __DIR__ . '/schema.sql' : dirname(WEB) . '/db/schema.sql');
    if (!is_readable($schema)) {
        fwrite(STDERR, "[instalar] La base está vacía y no encuentro el esquema: $schema (usar --schema=...)\n");
        exit(1);
    }
    db_ejecutar_sql((string)file_get_contents($schema));
    $log('Esquema importado.');
} else {
    $log('La base ya tiene el esquema.');
}

// 2. Clave de firma
$ruta = (string)cfg('oidc.clave_privada', '');
if ($ruta === '') {
    fwrite(STDERR, "[instalar] Falta oidc.clave_privada en la configuración.\n");
    exit(1);
}
if (!is_file($ruta)) {
    if (!is_dir(dirname($ruta))) {
        mkdir(dirname($ruta), 0700, true);
    }
    file_put_contents($ruta, generar_clave_rsa());
    chmod($ruta, 0600);
    $log('Clave de firma generada en ' . $ruta . ' (600).');
} else {
    $log('La clave de firma ya existe (kid ' . oidc_claves()['jwk']['kid'] . ').');
}

// 3. Super admins iniciales
foreach ((array)cfg('superadmins_iniciales', []) as $email) {
    $email = mb_strtolower(trim((string)$email));
    if ($email === '') {
        continue;
    }
    if (usuario_por_email($email) === null) {
        crear_usuario($email, explode('@', $email)[0], 'superadmin');
        auditar(null, 'usuario_creado', null, $email, ['rol' => 'superadmin', 'origen' => 'instalación']);
        $log("Super admin creado: $email (entra con Google).");
    }
}

// 4. Catálogo
if ((int)qv('SELECT COUNT(*) FROM aplicaciones') === 0) {
    $t = ahora();
    foreach (require __DIR__ . '/catalogo.php' as [$clave, $nombre, $desc, $cat, $orden, $url, $icono, $color, $roles]) {
        qx('INSERT INTO aplicaciones (clave, nombre, descripcion, categoria, url, icono, color, estado, orden, roles,
                                      rol_superadmin, creado_en, actualizado_en)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$clave, $nombre, $desc, $cat, $url, $icono, $color, 'activa', $orden, json_encode($roles), '', $t, $t]);
    }
    $log('Catálogo inicial cargado (' . count(require __DIR__ . '/catalogo.php') . ' aplicaciones).');
} else {
    $log('El catálogo ya tiene aplicaciones.');
}

$log('Listo.');
