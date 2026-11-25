-- ============================================================
--  ITHub — esquema de la base (MariaDB 10.6+ / MySQL 8)
--
--  Se importa UNA vez sobre una base vacía (bin/deploy.py schema o
--  app/instalar.php). Los cambios posteriores van en migraciones numeradas
--  (app/db/migracion-NNN-....sql), nunca editando esto a mano en producción.
--
--  Portabilidad: el mismo archivo arma la base SQLite en memoria de los tests
--  y la base local de desarrollo (sql_a_sqlite() en db.php). Por eso:
--    · los índices van como CREATE INDEX aparte, no como KEY dentro de la tabla;
--    · no hay ENUM: los valores válidos se controlan en PHP;
--    · las fechas son DATETIME y las arma PHP (ahora()), no la base.
--  Lo único que se traduce es AUTO_INCREMENT y la cola ENGINE=... de cada tabla.
--
--  Tablas:
--    usuarios        personas que entran a ITHub (cuentas @dominio de Google)
--    aplicaciones    catálogo de apps: tarjeta del panel + cliente OpenID Connect
--    permisos        qué rol tiene cada usuario en cada aplicación
--    sesiones        sesión única de ITHub (la que comparten todas las apps)
--    sesion_apps     a qué apps se entró con cada sesión (para el cierre en todas)
--    oidc_codigos    códigos de autorización de un solo uso (60 s)
--    oidc_tokens     access tokens del endpoint userinfo (1 h)
--    auditoria       quién hizo qué y cuándo
-- ============================================================

CREATE TABLE usuarios (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(190) NOT NULL,
    nombre          VARCHAR(120) NOT NULL DEFAULT '',
    avatar_url      VARCHAR(500) NULL,
    -- 'superadmin' | 'admin' | 'usuario' (ROLES_PORTAL en auth.php)
    rol             VARCHAR(20)  NOT NULL DEFAULT 'usuario',
    activo          TINYINT      NOT NULL DEFAULT 1,
    ultimo_acceso   DATETIME     NULL,
    creado_en       DATETIME     NOT NULL,
    actualizado_en  DATETIME     NOT NULL,
    UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aplicaciones (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    -- Identificador corto y fijo (facturacion, crm, remitos...). Es también
    -- el client_id de OpenID Connect.
    clave           VARCHAR(40)  NOT NULL,
    nombre          VARCHAR(80)  NOT NULL,
    descripcion     VARCHAR(255) NOT NULL DEFAULT '',
    categoria       VARCHAR(40)  NOT NULL DEFAULT 'General',
    -- A dónde lleva la tarjeta del panel (la pantalla de ingreso de la app).
    url             VARCHAR(500) NOT NULL,
    icono           VARCHAR(40)  NOT NULL DEFAULT 'app',
    color           VARCHAR(20)  NOT NULL DEFAULT 'violeta',
    -- 'activa' | 'mantenimiento' | 'proximamente'
    estado          VARCHAR(20)  NOT NULL DEFAULT 'activa',
    orden           INT          NOT NULL DEFAULT 100,
    -- Roles válidos de la app, en JSON (["admin","operador"]). El primero es
    -- el de mayor jerarquía.
    roles           TEXT         NOT NULL,
    -- Rol con el que entra un super admin sin permiso explícito. Vacío = el primero.
    rol_superadmin  VARCHAR(40)  NOT NULL DEFAULT '',
    -- OpenID Connect. Sin secreto la app no puede canjear códigos: la tarjeta
    -- funciona igual (lleva a la URL), pero sin sesión única.
    secreto_hash    CHAR(64)     NULL,
    redirect_uris   TEXT         NULL,      -- una por línea, coincidencia exacta
    logout_uris     TEXT         NULL,      -- post_logout_redirect_uri permitidas
    backchannel_uri VARCHAR(500) NULL,      -- adonde se avisa el cierre de sesión
    creado_en       DATETIME     NOT NULL,
    actualizado_en  DATETIME     NOT NULL,
    UNIQUE (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    aplicacion_id   INT UNSIGNED NOT NULL,
    rol             VARCHAR(40)  NOT NULL,
    otorgado_por    INT UNSIGNED NULL,
    creado_en       DATETIME     NOT NULL,
    actualizado_en  DATETIME     NOT NULL,
    UNIQUE (usuario_id, aplicacion_id),
    FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)     ON DELETE CASCADE,
    FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sesiones (
    -- Identificador público de la sesión (32 hex). Viaja a las apps como `sid`
    -- en el id_token; la cookie del navegador es otra cosa (la de PHP).
    id              CHAR(32)     NOT NULL PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    creada_en       DATETIME     NOT NULL,
    ultima_actividad DATETIME    NOT NULL,
    ip              VARCHAR(45)  NULL,
    user_agent      VARCHAR(255) NULL,
    cerrada_en      DATETIME     NULL,
    -- 'salida' | 'salida_app' | 'revocada' | 'vencida' | 'usuario_inactivo' | 'todas'
    motivo_cierre   VARCHAR(40)  NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX ix_sesiones_usuario ON sesiones (usuario_id, cerrada_en);

CREATE TABLE sesion_apps (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sesion_id       CHAR(32)     NOT NULL,
    aplicacion_id   INT UNSIGNED NOT NULL,
    rol             VARCHAR(40)  NOT NULL,
    primer_ingreso  DATETIME     NOT NULL,
    ultimo_ingreso  DATETIME     NOT NULL,
    UNIQUE (sesion_id, aplicacion_id),
    FOREIGN KEY (sesion_id)     REFERENCES sesiones(id)     ON DELETE CASCADE,
    FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oidc_codigos (
    codigo_hash     CHAR(64)     NOT NULL PRIMARY KEY,
    aplicacion_id   INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED NOT NULL,
    sesion_id       CHAR(32)     NOT NULL,
    redirect_uri    VARCHAR(500) NOT NULL,
    scope           VARCHAR(255) NOT NULL,
    nonce           VARCHAR(255) NULL,
    code_challenge  VARCHAR(128) NULL,
    creado_en       DATETIME     NOT NULL,
    expira_en       DATETIME     NOT NULL,
    usado_en        DATETIME     NULL,
    FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX ix_oidc_codigos_expira ON oidc_codigos (expira_en);

CREATE TABLE oidc_tokens (
    token_hash      CHAR(64)     NOT NULL PRIMARY KEY,
    aplicacion_id   INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED NOT NULL,
    sesion_id       CHAR(32)     NOT NULL,
    scope           VARCHAR(255) NOT NULL,
    creado_en       DATETIME     NOT NULL,
    expira_en       DATETIME     NOT NULL,
    FOREIGN KEY (aplicacion_id) REFERENCES aplicaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX ix_oidc_tokens_expira ON oidc_tokens (expira_en);

CREATE TABLE auditoria (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NULL,      -- quién lo hizo (NULL = sistema o anónimo)
    accion          VARCHAR(40)  NOT NULL,  -- ver ACCIONES_AUDITORIA en auditoria.php
    aplicacion_id   INT UNSIGNED NULL,
    objetivo        VARCHAR(190) NULL,      -- sobre quién o qué (un email, una clave)
    detalle         TEXT         NULL,      -- JSON con el resto
    ip              VARCHAR(45)  NULL,
    creado_en       DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX ix_auditoria_fecha   ON auditoria (creado_en);
CREATE INDEX ix_auditoria_usuario ON auditoria (usuario_id, creado_en);
