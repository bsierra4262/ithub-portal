<?php
// Usuarios: filtros y tabla (en el celular, tarjetas).
?>
<div class="cab">
  <div>
    <h1>Usuarios</h1>
    <p class="cab-sub">Quién entra a ITHub, con qué rol y a cuántas aplicaciones.</p>
  </div>
  <a class="btn" href="<?= e(url('usuario-form')) ?>"><?= icono('mas') ?> Nuevo usuario</a>
</div>

<form class="filtros" method="get" action="<?= e(url('usuarios')) ?>">
  <label class="buscador buscador-sm">
    <?= icono('buscar') ?><span class="sr">Buscar</span>
    <input type="search" name="q" value="<?= e($buscar) ?>" placeholder="Nombre o email">
  </label>
  <label><span class="sr">Rol</span>
    <select name="rol">
      <option value="">Todos los roles</option>
      <?php foreach (ROLES_PORTAL as $k => $v): ?><option value="<?= e($k) ?>"<?= $rol === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label><span class="sr">Estado</span>
    <select name="estado">
      <option value="">Activos e inactivos</option>
      <option value="activos"<?= $estado === 'activos' ? ' selected' : '' ?>>Sólo activos</option>
      <option value="inactivos"<?= $estado === 'inactivos' ? ' selected' : '' ?>>Sólo inactivos</option>
    </select>
  </label>
  <button class="btn btn-sec" type="submit">Filtrar</button>
  <?php if ($buscar !== '' || $rol !== '' || $estado !== ''): ?><a class="link" href="<?= e(url('usuarios')) ?>">Limpiar</a><?php endif; ?>
</form>

<div class="tabla-caja">
<table class="tabla">
  <thead>
    <tr><th>Persona</th><th>Rol en ITHub</th><th>Aplicaciones</th><th>Último ingreso</th><th>Estado</th><th><span class="sr">Acciones</span></th></tr>
  </thead>
  <tbody>
  <?php if (!$usuarios): ?>
    <tr><td colspan="6" class="vacio">No hay usuarios con esos filtros.</td></tr>
  <?php endif; ?>
  <?php foreach ($usuarios as $u): $editable = puede_editar_usuario($yo, $u); ?>
    <tr class="<?= (int)$u['activo'] === 1 ? '' : 'fila-off' ?>">
      <td data-th="Persona">
        <span class="persona">
          <?php if (!empty($u['avatar_url'])): ?>
            <img class="avatar" src="<?= e($u['avatar_url']) ?>" alt="" width="34" height="34" referrerpolicy="no-referrer">
          <?php else: ?>
            <span class="avatar avatar-ini"><?= e(iniciales($u['nombre'])) ?></span>
          <?php endif; ?>
          <span><strong><?= e($u['nombre']) ?></strong><span class="muted"><?= e($u['email']) ?></span></span>
        </span>
      </td>
      <td data-th="Rol"><span class="badge badge-<?= e($u['rol']) ?>"><?= e(ROLES_PORTAL[$u['rol']] ?? $u['rol']) ?></span></td>
      <td data-th="Aplicaciones">
        <?php if ($u['rol'] === 'superadmin'): ?>Todas<?php else: ?><?= (int)$u['apps'] ?> de <?= $total_apps ?><?php endif; ?>
      </td>
      <td data-th="Último ingreso"><span><?= e(hace($u['ultimo_acceso'])) ?><?php if ((int)$u['sesiones'] > 0): ?> <span class="punto-on" title="Con sesión abierta"></span><?php endif; ?></span></td>
      <td data-th="Estado"><?= (int)$u['activo'] === 1 ? '<span class="badge badge-ok">Activo</span>' : '<span class="badge badge-suave">Inactivo</span>' ?></td>
      <td class="acciones">
        <?php if ($editable): ?><a class="btn btn-sec btn-sm" href="<?= e(url('usuario-form', ['id' => $u['id']])) ?>"><?= icono('editar') ?> Editar</a><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="muted pie-tabla"><?= count($usuarios) ?> <?= count($usuarios) === 1 ? 'usuario' : 'usuarios' ?></p>
