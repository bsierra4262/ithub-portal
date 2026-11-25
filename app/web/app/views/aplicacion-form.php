<?php
// Alta / edición de una aplicación: tarjeta, roles y OpenID Connect.
$nueva = $app === null;
$err = static fn(string $k): string => isset($errores[$k]) ? '<em class="error">' . e($errores[$k]) . '</em>' : '';
$cls = static fn(string $k): string => isset($errores[$k]) ? ' con-error' : '';
?>
<div class="cab">
  <div>
    <a class="volver" href="<?= e(url('aplicaciones')) ?>"><?= icono('flecha', 'ico ico-volver') ?> Aplicaciones</a>
    <h1><?= $nueva ? 'Nueva aplicación' : e($app['nombre']) ?></h1>
    <?php if (!$nueva): ?><p class="cab-sub">client_id <code><?= e($app['clave']) ?></code> · <?= $app['sso'] ? 'conectada al ingreso único' : 'sin ingreso único todavía' ?></p><?php endif; ?>
  </div>
</div>

<?php if ($secreto !== null): ?>
<section class="panel-caja secreto" id="secreto">
  <h2 class="caja-tit"><?= icono('llave') ?> Secreto nuevo de <?= e($app['nombre']) ?></h2>
  <p>Copialo ahora y guardalo en la configuración de la app (y en su repo de claves). <strong>No se vuelve a mostrar</strong>: si se pierde, se genera otro.</p>
  <div class="copiar">
    <code data-copiable><?= e($secreto) ?></code>
    <button class="btn btn-sm" type="button" data-copiar>Copiar</button>
  </div>
</section>
<?php endif; ?>

<form method="post" action="<?= e(url('aplicacion-guardar')) ?>" class="form" novalidate>
  <?= csrf_campo() ?>
  <input type="hidden" name="id" value="<?= $nueva ? 0 : (int)$app['id'] ?>">

  <section class="panel-caja">
    <h2 class="caja-tit">Tarjeta del panel</h2>
    <div class="campos">
      <label class="campo<?= $cls('clave') ?>">
        <span>Clave (client_id)</span>
        <?php if ($nueva): ?>
          <input type="text" name="clave" value="<?= e($v['clave']) ?>" placeholder="crm" pattern="[a-z][a-z0-9-]{1,39}" required>
          <small>Corta, en minúsculas. No se cambia después: es con lo que la app se presenta ante ITHub.</small>
        <?php else: ?>
          <input type="text" value="<?= e($app['clave']) ?>" disabled>
        <?php endif; ?>
        <?= $err('clave') ?>
      </label>
      <label class="campo<?= $cls('nombre') ?>">
        <span>Nombre</span>
        <input type="text" name="nombre" value="<?= e($v['nombre']) ?>" maxlength="80" required>
        <?= $err('nombre') ?>
      </label>
      <label class="campo campo-ancho<?= $cls('descripcion') ?>">
        <span>Descripción</span>
        <input type="text" name="descripcion" value="<?= e($v['descripcion']) ?>" maxlength="255" placeholder="Qué se hace en esta app, en una línea">
        <?= $err('descripcion') ?>
      </label>
      <label class="campo campo-ancho<?= $cls('url') ?>">
        <span>Dirección de la app</span>
        <input type="url" name="url" value="<?= e($v['url']) ?>" placeholder="https://..." required>
        <small>Adónde lleva la tarjeta. Si la app ya está conectada a ITHub, su pantalla de ingreso entra sola.</small>
        <?= $err('url') ?>
      </label>
      <label class="campo">
        <span>Categoría</span>
        <input type="text" name="categoria" value="<?= e($v['categoria']) ?>" list="categorias" maxlength="40" placeholder="Gestión">
        <datalist id="categorias"><?php foreach ($categorias as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        <small>Agrupa las tarjetas en el panel.</small>
      </label>
      <label class="campo">
        <span>Orden</span>
        <input type="number" name="orden" value="<?= (int)$v['orden'] ?>" min="0" max="9999">
        <small>Posición en el panel, de menor a mayor.</small>
      </label>
      <label class="campo<?= $cls('estado') ?>">
        <span>Estado</span>
        <select name="estado">
          <?php foreach (ESTADOS_APP as $k => $t): ?><option value="<?= e($k) ?>"<?= $v['estado'] === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
        <small>En mantenimiento sólo entran los super admin. Próximamente: nadie.</small>
        <?= $err('estado') ?>
      </label>
      <div class="campo campo-ancho">
        <span>Ícono</span>
        <div class="selector-iconos" role="radiogroup" aria-label="Ícono">
          <?php foreach (ICONOS_APP as $k => [$t]): ?>
            <label class="opcion-icono" title="<?= e($t) ?>">
              <input type="radio" name="icono" value="<?= e($k) ?>"<?= $v['icono'] === $k ? ' checked' : '' ?>>
              <span><?= icono($k) ?><span class="sr"><?= e($t) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="campo campo-ancho">
        <span>Color</span>
        <div class="selector-colores" role="radiogroup" aria-label="Color">
          <?php foreach (COLORES_APP as $k => $t): ?>
            <label class="opcion-color" title="<?= e($t) ?>">
              <input type="radio" name="color" value="<?= e($k) ?>"<?= $v['color'] === $k ? ' checked' : '' ?>>
              <span class="c-<?= e($k) ?>"><span class="sr"><?= e($t) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="panel-caja">
    <h2 class="caja-tit">Roles</h2>
    <div class="campos">
      <label class="campo campo-ancho<?= $cls('roles') ?>">
        <span>Roles de la app</span>
        <input type="text" name="roles" value="<?= e($v['roles']) ?>" placeholder="admin, operador">
        <small>Con los MISMOS nombres que usa la app, separados por coma; el de más jerarquía primero. Llegan a la app en el claim <code>rol</code>.</small>
        <?= $err('roles') ?>
      </label>
      <label class="campo<?= $cls('rol_superadmin') ?>">
        <span>Rol de un super admin</span>
        <input type="text" name="rol_superadmin" value="<?= e($v['rol_superadmin']) ?>" placeholder="(el primero)">
        <small>Con qué rol entra un super admin sin permiso explícito.</small>
        <?= $err('rol_superadmin') ?>
      </label>
    </div>
  </section>

  <section class="panel-caja">
    <h2 class="caja-tit">Ingreso único (OpenID Connect)</h2>
    <p class="muted caja-sub">Lo que la app tiene que configurar está en <code>md/INTEGRAR-APLICACION.md</code>. Issuer: <code><?= e(oidc_issuer()) ?></code></p>
    <div class="campos">
      <label class="campo campo-ancho<?= $cls('redirect_uris') ?>">
        <span>URIs de vuelta (redirect_uri)</span>
        <textarea name="redirect_uris" rows="3" placeholder="https://app.intellihelp.tech/ithub-callback"><?= e($v['redirect_uris'] ?? '') ?></textarea>
        <small>Una por línea. Tienen que coincidir EXACTO con las que manda la app.</small>
        <?= $err('redirect_uris') ?>
      </label>
      <label class="campo campo-ancho">
        <span>URIs después de salir (post_logout_redirect_uri)</span>
        <textarea name="logout_uris" rows="2" placeholder="https://app.intellihelp.tech/login"><?= e($v['logout_uris'] ?? '') ?></textarea>
      </label>
      <label class="campo campo-ancho<?= $cls('backchannel_uri') ?>">
        <span>Aviso de cierre de sesión (back-channel logout)</span>
        <input type="url" name="backchannel_uri" value="<?= e($v['backchannel_uri'] ?? '') ?>" placeholder="https://app.intellihelp.tech/ithub-logout">
        <small>ITHub le avisa acá cuando la sesión se cierra, para que la app corte la suya.</small>
        <?= $err('backchannel_uri') ?>
      </label>
    </div>
  </section>

  <div class="form-acciones">
    <a class="btn btn-sec" href="<?= e(url('aplicaciones')) ?>">Volver</a>
    <button class="btn" type="submit"><?= icono('check') ?> <?= $nueva ? 'Crear aplicación' : 'Guardar cambios' ?></button>
  </div>
</form>

<?php if (!$nueva): ?>
<section class="panel-caja zona">
  <div>
    <h2 class="caja-tit"><?= icono('llave') ?> Secreto de la app</h2>
    <p class="muted"><?= !empty($app['secreto_hash']) ? 'La app ya tiene un secreto. Generar otro deja de aceptar el anterior en el acto: la app no puede canjear ingresos hasta que se le cargue el nuevo.' : 'Todavía no tiene secreto: sin él la app no puede usar el ingreso único.' ?></p>
  </div>
  <form method="post" action="<?= e(url('aplicacion-secreto')) ?>"<?= !empty($app['secreto_hash']) ? ' data-confirmar="¿Generar un secreto nuevo? El actual deja de funcionar en el acto."' : '' ?>>
    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
    <button class="btn <?= !empty($app['secreto_hash']) ? 'btn-peligro' : '' ?>" type="submit"><?= !empty($app['secreto_hash']) ? 'Regenerar secreto' : 'Generar secreto' ?></button>
  </form>
</section>
<?php endif; ?>
