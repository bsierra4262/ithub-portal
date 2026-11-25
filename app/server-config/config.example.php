<?php
// ============================================================
//  PLANTILLA de configuración — copiá este archivo a config.php y completá
//  los valores reales. config.php NO se versiona (tiene secretos) → ver .gitignore.
//
//  En producción se despliega FUERA del web root:
//      /home/USUARIO/ithub_config/config.php     (chmod 600)
//  y lo encuentra solo bootstrap.php (ver cargar_config()). Si el hosting la
//  tuviera en otro lado, se indica con la variable de entorno ITHUB_CONFIG.
//  En el repo, bootstrap.php también prueba app/server-config/config.php y
//  app/web/config.local.php (desarrollo local).
// ============================================================
return [
    // Conexión a la base. En Hostinger la base está en el mismo servidor:
    // host 127.0.0.1. Para desarrollo local se puede usar SQLite:
    //   'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/ithub.sqlite'],
    'db' => [
        'driver'  => 'mysql',
        'host'    => '127.0.0.1',
        'name'    => 'TU_BASE_DE_DATOS',
        'user'    => 'TU_USUARIO_DB',
        'pass'    => 'TU_PASSWORD_DB',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'     => 'ITHub',
        'org'      => 'INTELLIHELP',
        // URL canónica, sin barra final. Es también el `issuer` de OpenID
        // Connect: si cambia, todas las apps conectadas dejan de aceptar los
        // tokens hasta que se les actualice la configuración.
        'url'      => 'https://ithub.intellihelp.tech',
        // 'dev' muestra los errores en pantalla y habilita el ingreso de
        // desarrollo (sólo desde 127.0.0.1). En producción SIEMPRE 'prod'.
        'env'      => 'prod',
        'timezone' => 'America/Argentina/Buenos_Aires',
        // Sesión única de ITHub: inactividad y duración máxima (segundos).
        // Las apps tienen sus propios tiempos; esto rige el portal y la
        // entrada a una app nueva sin volver a pasar por Google.
        'idle_ttl' => 7200,      // 2 h sin actividad en el portal
        'abs_ttl'  => 43200,     // 12 h como máximo
        // Proxies propios delante del portal (IPs). Vacío en Hostinger.
        'proxies_confiables' => [],
    ],
    'google' => [
        // Login con Google Workspace (OAuth 2.0, app Interna).
        // Consola: Google Cloud → API y servicios → Credenciales.
        // URI de redireccionamiento autorizada: <app.url>/google-callback
        'client_id'      => 'TU_CLIENT_ID.apps.googleusercontent.com',
        'client_secret'  => 'GOCSPX-...',                 // NUNCA subir a git
        'allowed_domain' => 'intellihelp.tech',           // sólo cuentas del dominio
        // true: una cuenta del dominio que entra por primera vez se crea como
        // 'usuario' sin acceso a ninguna app (un admin le asigna después).
        // false: sólo entran las cuentas dadas de alta a mano.
        'auto_provision' => true,
    ],
    'oidc' => [
        // Clave privada RSA con la que se firman los id_token. La genera
        // app/instalar.php si no existe. Fuera del web root y con chmod 600.
        'clave_privada'  => '/home/USUARIO/ithub_config/oidc-privada.pem',
        'codigo_ttl'     => 60,       // vida del código de autorización (s)
        'id_token_ttl'   => 300,      // vida del id_token (s)
        'access_ttl'     => 3600,     // vida del access token de userinfo (s)
    ],
    // Las cuentas que app/instalar.php crea como super admin la primera vez.
    'superadmins_iniciales' => [
        'bsierra@intellihelp.tech',
        'nbaldelli@intellihelp.tech',
    ],
];
