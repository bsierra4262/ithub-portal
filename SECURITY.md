# Política de seguridad

## Reporte de vulnerabilidades

Ante una vulnerabilidad de seguridad, **no debe abrirse un issue público**. El reporte se
realiza de forma privada a **bsierra@intellihelp.tech**.

Se solicita incluir, en la medida de lo posible: descripción, pasos de reproducción, impacto
y una posible mitigación.

## Acceso

- **Sólo Google Workspace**, sin contraseñas. Se valida en el `id_token` de Google el emisor,
  la audiencia, el vencimiento, el `nonce`, el email verificado, el `hd` y el **dominio exacto**
  del email (no un sufijo: `x@intellihelp.tech.otro.com` no entra).
- El canje con Google usa **PKCE** y `state`; el código de Google nunca pasa por el navegador
  dos veces.
- La sesión se valida contra la base en cada pedido: un cierre o una baja rige en el pedido
  siguiente. Inactividad (2 h) y duración máxima (12 h) configurables.
- Cookies de sesión `HttpOnly`, `Secure` y `SameSite=Lax` (Lax es necesario para que el
  ingreso único funcione desde otra app sin pedir login de nuevo).

## Proveedor OpenID Connect

- `redirect_uri` con **coincidencia exacta**; si la app o la URI no coinciden, el error se
  muestra en ITHub (no hay redirector abierto).
- Códigos de autorización de **un solo uso y 60 segundos**, consumidos de forma atómica; el
  reuso revoca los tokens emitidos.
- Secretos de las apps, códigos y access tokens se guardan como **SHA-256**: una copia de la
  base no permite hacerse pasar por nadie.
- `id_token` y `logout_token` firmados con **RS256**; sólo se acepta ese algoritmo (`none` nunca).
- La clave privada vive fuera del web root con permisos `600`.
- Quitar un permiso, desactivar a una persona o cerrar una sesión avisa a las apps
  (**back-channel logout**) y borra los access tokens.

## Aplicación

- Todo formulario lleva token **CSRF**; las acciones que cambian algo son sólo `POST`.
- **Content-Security-Policy** estricta: sin scripts ni estilos en línea, sin iframes
  (`frame-ancestors 'none'`).
- El código no se sirve por HTTP (`app/` negado por `.htaccess`); el front controller sólo
  incluye controladores registrados en la tabla de rutas.
- Las redirecciones después del login sólo aceptan rutas internas.
- La auditoría no guarda secretos, códigos ni tokens.
- La ruta de desarrollo `/dev-login` sólo existe con `app.env = 'dev'` y desde `127.0.0.1`.
