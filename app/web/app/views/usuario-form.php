<?php
// Alta / edición de usuario con la matriz de permisos por aplicación.
$nuevo = $usuario === null;
$val = static fn(string $k, $def = '') => $previo[$k] ?? ($usuario[$k] ?? $def);
$rolActual = (string)$val('rol', 'usuario');
$esSuper = $rolActual === 'superadmin';
$soyYo = !$nuevo && (int)$usuario['id'] === $yo['id'];
?>
<div class="cab">
  <div>
    <a class="volver" href="<?= e(url('usuarios')) ?>">← Usuarios</a>
    <h1><?= $nuevo ? 'Nuevo usuario' : e($usuario['nombre']) ?></h1>
    <?php if (!$nuevo): ?><p class="cab-sub"><?= e($usuario['email']) ?> · alta <?= e(fecha_legible($usuario['creado_en'], false)) ?> · último ingreso <?= e(hace($usuario['ultimo_acceso'])) ?></p><?php endif; ?>
  </div>
  <?php if (!$nuevo && $sesiones_abiertas > 0 && !$soyYo): ?>
  <form method="post" action="<?= e(url('sesion-cerrar')) ?>" data-confirmar="¿Cerrar las <?= $sesiones_abiertas ?> sesiones abiertas de <?= e($usuario['nombre']) ?>? Se cierran también en las aplicaciones.">
    <?= csrf_campo() ?><input type="hidden" name="usuario" value="<?= (int)$usuario['id'] ?>"><input type="hidden" name="volver" value="sesiones">
    <button class="btn btn-sec" type="submit"><?= icono('salir') ?> Cerrar sus sesiones (<?= $sesiones_abiertas ?>)</button>
  </form>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(url('usuario-guardar')) ?>" class="form" novalidate>
  <?= csrf_campo() ?>
  <input type="hidden" name="id" value="<?= $nuevo ? 0 : (int)$usuario['id'] ?>">

  <section class="panel-caja">
    <h2 class="caja-tit">Datos</h2>
    <div class="campos">
      <label class="campo<?= isset($errores['email']) ? ' con-error' : '' ?>">
        <span>Email</span>
        <?php if ($nuevo): ?>
          <input type="email" name="email" value="<?= e($val('email')) ?>" placeholder="nombre@<?= e($dominio) ?>" required autocomplete="off">
          <small>Cuenta de Google Workspace @<?= e($dominio) ?>. Con esa cuenta entra a ITHub.</small>
        <?php else: ?>
          <input type="email" value="<?= e($usuario['email']) ?>" disabled>
          <small>El email no se cambia: es la identidad de Google de la persona.</small>
        <?php endif; ?>
        <?php if (isset($errores['email'])): ?><em class="error"><?= e($errores['email']) ?></em><?php endif; ?>
      </label>
      <label class="campo<?= isset($errores['nombre']) ? ' con-error' : '' ?>">
        <span>Nombre y apellido</span>
        <input type="text" name="nombre" value="<?= e($val('nombre')) ?>" maxlength="120" required>
        <small>Se reemplaza por el de Google en el primer ingreso.</small>
        <?php if (isset($errores['nombre'])): ?><em class="error"><?= e($errores['nombre']) ?></em><?php endif; ?>
      </label>
      <label class="campo<?= isset($errores['rol']) ? ' con-error' : '' ?>">
        <span>Rol en ITHub</span>
        <select name="rol" data-rol-portal<?= $soyYo ? ' disabled' : '' ?>>
          <?php foreach (ROLES_PORTAL as $k => $v):
            if ($k === 'superadmin' && !es_superadmin($yo)) { continue; } ?>
            <option value="<?= e($k) ?>"<?= $rolActual === $k ? ' selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($soyYo): ?><input type="hidden" name="rol" value="<?= e($rolActual) ?>"><?php endif; ?>
        <small>Usuario: sólo su panel. Administrador: además usuarios, sesiones y auditoría. Super admin: todo y entra a todas las apps.</small>
        <?php if (isset($errores['rol'])): ?><em class="error"><?= e($errores['rol']) ?></em><?php endif; ?>
      </label>
      <div class="campo<?= isset($errores['activo']) ? ' con-error' : '' ?>">
        <span>Estado</span>
        <label class="interruptor">
          <input type="checkbox" name="activo" value="1"<?= (int)$val('activo', 1) === 1 ? ' checked' : '' ?><?= $soyYo ? ' disabled' : '' ?>>
          <span>Activo</span>
        </label>
        <?php if ($soyYo): ?><input type="hidden" name="activo" value="1"><?php endif; ?>
        <small>Desactivarlo cierra en el acto todas sus sesiones, en ITHub y en las aplicaciones.</small>
        <?php if (isset($errores['activo'])): ?><em class="error"><?= e($errores['activo']) ?></em><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="panel-caja">
    <h2 class="caja-tit">Permisos por aplicación</h2>
    <p class="muted caja-sub" data-nota-super<?= $esSuper ? '' : ' hidden' ?>>Como super admin entra a todas las aplicaciones activas. Lo que elijas acá define su rol en cada una; en «Automático» entra con el rol de administrador de la app.</p>
    <p class="muted caja-sub" data-nota-comun<?= $esSuper ? ' hidden' : '' ?>>Elegí con qué rol entra a cada aplicación. «Sin acceso» oculta la tarjeta del panel y bloquea el ingreso. Quitar una aplicación cierra su sesión en ella.</p>
    <div class="permisos">
      <?php foreach ($apps as $a): $p = (string)($permisos[$a['id']] ?? ''); ?>
        <div class="permiso<?= $p !== '' ? ' permiso-on' : '' ?>">
          <span class="mini-ico c-<?= e($a['color']) ?>"><?= icono($a['icono']) ?></span>
          <span class="permiso-txt">
            <strong><?= e($a['nombre']) ?></strong>
            <span class="muted"><?= e($a['categoria']) ?><?= $a['estado'] === 'mantenimiento' ? ' · en mantenimiento' : '' ?></span>
          </span>
          <label><span class="sr">Rol en <?= e($a['nombre']) ?></span>
            <select name="permiso[<?= (int)$a['id'] ?>]" data-permiso>
              <option value=""><?= $esSuper ? 'Automático' : 'Sin acceso' ?></option>
              <?php foreach ($a['roles'] as $r): ?><option value="<?= e($r) ?>"<?= $p === $r ? ' selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
            </select>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="form-acciones">
    <a class="btn btn-sec" href="<?= e(url('usuarios')) ?>">Cancelar</a>
    <button class="btn" type="submit"><?= icono('check') ?> <?= $nuevo ? 'Crear usuario' : 'Guardar cambios' ?></button>
  </div>
</form>
