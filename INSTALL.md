# Instalación

## Requisitos

- PHP 8.2 o superior con `pdo_mysql` (o `pdo_sqlite` en local), `openssl`, `curl` y `mbstring`.
- MariaDB 10.6+ / MySQL 8.
- Apache o LiteSpeed con `mod_rewrite` (Hostinger), HTTPS.
- Un cliente OAuth de Google Workspace de tipo **Interno**.

## 1. Google Cloud

En **Google Cloud → API y servicios → Credenciales**, un **ID de cliente de OAuth** de tipo
*Aplicación web*:

- Orígenes autorizados: `https://ithub.intellihelp.tech`
- URI de redireccionamiento autorizada: `https://ithub.intellihelp.tech/google-callback`

El `client_id` y el `client_secret` van a `config.php` (nunca al repositorio).

## 2. Hosting

1. Subdominio `ithub.intellihelp.tech` con su carpeta `public_html` (el web root es
   `app/web/` del repo).
2. Base MariaDB vacía y su usuario.
3. Carpeta de configuración **fuera del web root**: `/home/USUARIO/ithub_config/` (700).
4. Si el subdominio es nuevo, darlo de alta también en la zona interna del DNS de la oficina.

## 3. Configuración

```bash
cp app/server-config/config.example.php app/server-config/config.php
```

Completar base, `app.url`, Google y `oidc.clave_privada`
(`/home/USUARIO/ithub_config/oidc-privada.pem`). El archivo real se guarda también en el repo
privado `ithub-portal-claves`.

## 4. Deploy

```bash
python bin/deploy.py config --produccion     # sube config.php a ~/ithub_config (600)
python bin/deploy.py app --produccion        # sube el código con swap y php -l
python bin/deploy.py instalar --produccion   # esquema, clave de firma, super admins, catálogo
python bin/deploy.py test --produccion       # corre la batería contra el código desplegado
```

`instalar` es idempotente: sólo hace lo que falta. La clave de firma se genera **una vez**; si
se pierde o se cambia, las apps conectadas tienen que volver a leer `/oauth/jwks` (lo hacen
solas si el `kid` es nuevo).

Para volver a la versión anterior: `python bin/deploy.py app --produccion --volver`.

## 5. Primer ingreso

Entrar con una de las cuentas de `superadmins_iniciales`. Desde **Usuarios** se da de alta al
resto y se asignan los permisos por aplicación; desde **Aplicaciones** se conecta cada app al
ingreso único ([md/INTEGRAR-APLICACION.md](md/INTEGRAR-APLICACION.md)).

## Desarrollo local

```bash
# app/web/config.local.php: db.driver = sqlite, app.env = dev, app.url = http://127.0.0.1:8090
php app/web/app/cli/instalar.php
php -S 127.0.0.1:8090 -t app/web test/router.php
```

Con `app.env = dev` y desde `127.0.0.1` existe `/dev-login?email=...` para entrar sin Google
(sacar capturas, probar pantallas). En producción esa ruta no existe.

En Windows el PHP no trae las extensiones cargadas: agregar
`-d extension_dir="<carpeta de php>/ext" -d extension=openssl -d extension=pdo_sqlite -d extension=mbstring -d extension=curl`.
