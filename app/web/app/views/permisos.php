<?php
// Permisos: explicación de los dos niveles de rol + matriz personas × apps.
$filtrado = $buscar !== '' || $inactivos;
?>
<div class="cab">
  <div>
    <span class="eyebrow">Administración</span>
    <h1>Permisos</h1>
    <p class="cab-sub">Quién ve cada aplicación y con qué rol. Si una persona no tiene rol en una aplicación, la tarjeta no aparece en su panel y la aplicación no la deja entrar.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('usuario-form')) ?>"><?= icono('mas') ?> Dar de alta a una persona</a>
</div>

<div class="niveles">
  <section class="nivel">
    <span class="nivel-num">1</span>
    <div>
      <h2>Rol en ITHub</h2>
      <p class="muted">Qué puede hacer dentro del portal. Se cambia en <a href="<?= e(url('usuarios')) ?>">Usuarios</a>.</p>
      <ul class="nivel-lista">
        <li><span class="badge badge-usuario">Usuario</span> Ve su panel con las aplicaciones habilitadas.</li>
        <li><span class="badge badge-admin">Administrador</span> Además da de alta personas, asigna permisos y ve sesiones y auditoría.</li>
        <li><span class="badge badge-superadmin">Super admin</span> Todo, incluido el catálogo de aplicaciones. Entra a todas automáticamente.</li>
      </ul>
    </div>
  </section>
  <section class="nivel">
    <span class="nivel-num">2</span>
    <div>
      <h2>Rol en cada aplicación</h2>
      <p class="muted">Con qué rol entra a cada sistema. Los nombres de los roles son los mismos que usa cada aplicación (admin, operador, comercial…), así la aplicación los aplica tal cual. Se asignan en la tabla de abajo.</p>
      <ul class="nivel-lista">
        <li><span class="celda-demo celda-on">Admin</span> Tiene acceso con ese rol.</li>
        <li><span class="celda-demo">—</span> Sin acceso: no ve la aplicación.</li>
        <li><span class="celda-demo celda-auto">Automático</span> Super admin sin rol elegido: entra con el rol de administrador de la app.</li>
      </ul>
    </div>
  </section>
</div>

<form class="filtros" method="get" action="<?= e(url('permisos')) ?>">
  <label class="buscador buscador-sm">
    <?= icono('buscar') ?><span class="sr">Buscar persona</span>
    <input type="search" name="q" value="<?= e($buscar) ?>" placeholder="Buscar persona">
  </label>
  <label class="check"><input type="checkbox" name="inactivos" value="1"<?= $inactivos ? ' checked' : '' ?>> Incluir inactivos</label>
  <button class="btn btn-ghost" type="submit">Filtrar</button>
  <?php if ($filtrado): ?><a class="link" href="<?= e(url('permisos')) ?>">Limpiar</a><?php endif; ?>
</form>

<form method="post" action="<?= e(url('permisos-guardar')) ?>" class="card card-flush" data-matriz>
  <?= csrf_campo() ?>
  <input type="hidden" name="_q" value="<?= e($buscar) ?>">
  <input type="hidden" name="_inactivos" value="<?= $inactivos ? '1' : '' ?>">
  <div class="matriz-caja">
    <table class="matriz">
      <thead>
        <tr>
          <th class="matriz-persona" scope="col">Persona</th>
          <?php foreach ($apps as $a): ?>
          <th scope="col">
            <span class="matriz-app" title="<?= e($a['nombre']) ?>">
              <span class="mini-ico c-<?= e($a['color']) ?>"><?= icono($a['icono']) ?></span>
              <span class="matriz-app-nombre"><?= e($a['nombre']) ?></span>
              <span class="matriz-app-n"><?= (int)$con_acceso[$a['id']] ?> con acceso</span>
            </span>
          </th>
          <?php endforeach; ?>
          <th scope="col"><span class="sr">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$usuarios): ?>
        <tr><td colspan="<?= count($apps) + 2 ?>" class="vacio">No hay personas con ese filtro.</td></tr>
      <?php endif; ?>
      <?php foreach ($usuarios as $u):
        $uid = (int)$u['id'];
        $esSuper = $u['rol'] === 'superadmin';
        $editable = puede_editar_usuario($yo, $u);
      ?>
        <tr class="<?= (int)$u['activo'] === 1 ? '' : 'fila-off' ?>">
          <th class="matriz-persona" scope="row">
            <span class="persona">
              <?php if (!empty($u['avatar_url'])): ?>
                <img class="avatar sm" src="<?= e($u['avatar_url']) ?>" alt="" width="32" height="32" referrerpolicy="no-referrer">
              <?php else: ?>
                <span class="avatar sm avatar-ini"><?= e(iniciales($u['nombre'])) ?></span>
              <?php endif; ?>
              <span>
                <strong><?= e($u['nombre']) ?></strong>
                <span class="muted"><?= e($u['email']) ?></span>
                <span class="badge badge-<?= e($u['rol']) ?>"><?= e(ROLES_PORTAL[$u['rol']] ?? $u['rol']) ?></span>
              </span>
            </span>
          </th>
          <?php foreach ($apps as $a):
            $p = (string)($matriz[$uid][$a['id']] ?? '');
            $clase = $p !== '' ? 'celda-on' : ($esSuper ? 'celda-auto' : '');
          ?>
          <td class="celda <?= $clase ?>">
            <?php if ($editable): ?>
              <label><span class="sr">Rol de <?= e($u['nombre']) ?> en <?= e($a['nombre']) ?></span>
                <select name="permiso[<?= $uid ?>][<?= (int)$a['id'] ?>]" data-celda data-super="<?= $esSuper ? '1' : '0' ?>" data-inicial="<?= e($p) ?>">
                  <option value=""><?= $esSuper ? 'Automático' : '—' ?></option>
                  <?php foreach ($a['roles'] as $r): ?><option value="<?= e($r) ?>"<?= $p === $r ? ' selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
                </select>
              </label>
            <?php else: ?>
              <span class="celda-txt"><?= $p !== '' ? e(ucfirst($p)) : ($esSuper ? 'Automático' : '—') ?></span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td class="acciones">
            <a class="icon-btn" href="<?= e(url('vista-previa', ['usuario' => $uid])) ?>" title="Ver el panel como <?= e($u['nombre']) ?>" aria-label="Ver el panel como <?= e($u['nombre']) ?>"><?= icono('ojo') ?></a>
            <?php if ($editable): ?><a class="icon-btn" href="<?= e(url('usuario-form', ['id' => $uid])) ?>" title="Editar a <?= e($u['nombre']) ?>" aria-label="Editar a <?= e($u['nombre']) ?>"><?= icono('editar') ?></a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="matriz-pie">
    <span class="muted" data-matriz-estado>Elegí el rol en cada celda y guardá. Quitar una aplicación cierra la sesión de esa persona en ella.</span>
    <button class="btn btn-primary" type="submit"><?= icono('check') ?> Guardar permisos</button>
  </div>
</form>
