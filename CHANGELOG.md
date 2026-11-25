# Changelog

Todos los cambios notables de este proyecto se documentan acá.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/)
y el versionado, [SemVer](https://semver.org/lang/es/).

## 2026-11-25 · 0.2.2 · Login directo sin app pendiente

- «Para entrar a <app>…» sólo aparece cuando se llega al login desde una app (`/login?app=`).
  Abrir `ithub.intellihelp.tech/login` por cuenta propia descarta cualquier pedido viejo que
  hubiera quedado en el navegador, y al ingresar se va al panel.

## 2026-11-25 · 0.2.1 · Salir vuelve a ITHub

- Salir desde cualquier app conectada cierra la sesión única y deja siempre en el login de
  ITHub con el aviso «Cerraste sesión en ITHub y en todas las aplicaciones». Antes volvía al
  login de la app, que reenviaba en el acto a ITHub con «Para entrar a <app>…» y, al ingresar,
  llevaba a esa app en vez del panel.

## 2026-11-25 · 0.2.0 · Rediseño y pantalla de Permisos

- **Rediseño completo** con la estructura del Portal de RRHH: barra lateral violeta con
  secciones (General, Administración, Configuración), barra superior con el título, banner de
  bienvenida con el resumen del día y tarjetas de aplicaciones dentro de una caja. Login partido
  igual al de RRHH. Rótulos con la barrita lima de la web institucional.
- **Saira servida desde el portal** (`assets/fonts`): la CSP ya no permite fuentes de Google.
- **Permisos**: matriz personas × aplicaciones editable en una sola pantalla, con la
  explicación de los dos niveles de rol, búsqueda, inactivos opcionales, marca de celdas
  cambiadas, contador de cambios sin guardar y aviso al salir sin guardar. Respeta las mismas
  reglas que la edición de un usuario (un admin no toca a un super admin) y quitar una app
  cierra la sesión de esa persona en ella.
- **Ver como**: el panel tal como lo ve otra persona, de sólo lectura.
- **Usuarios** muestra los íconos de las aplicaciones que ve cada persona.

## 2026-11-25 · 0.1.0 · Portal y proveedor de identidad

Primera versión de ITHub como portal único de Intellihelp (etapa 1).

- **Login sólo con Google Workspace** (Authorization Code + PKCE, server-side). Se valida el
  `hd` y el dominio exacto del email en el `id_token`; sin contraseñas.
- **Sesión única** guardada en la base (`sesiones`): inactividad y duración máxima, cierre
  desde el panel, cierre de todas las sesiones propias.
- **Panel** con una tarjeta por aplicación, rol de la persona en cada una, filtros por
  categoría y buscador. Resumen del día para administradores.
- **Usuarios** con rol en ITHub (super admin, administrador, usuario) y **matriz de permisos
  por aplicación**. Reglas: nadie se desactiva ni se cambia el rol a sí mismo, siempre queda
  un super admin, desactivar cierra todas las sesiones, quitar una app cierra la sesión en esa app.
- **Catálogo de aplicaciones** editable: tarjeta, categoría, orden, estado (activa,
  mantenimiento, próximamente), roles y datos de OpenID Connect. Secreto generado una sola vez
  y guardado como SHA-256.
- **Proveedor OpenID Connect**: discovery, authorize, token, userinfo, jwks, logout
  (RP-initiated) y **back-channel logout** a las apps. `id_token` RS256 con el claim `rol` de
  la app.
- **Auditoría** de ingresos, salidas, entradas a cada app, cambios de usuarios, permisos y
  aplicaciones, con filtros y paginado.
- Catálogo inicial con las 11 aplicaciones de Intellihelp.
- 45 tests sobre SQLite en memoria; flujo completo probado por HTTP y firma verificada con
  `openssl`.
