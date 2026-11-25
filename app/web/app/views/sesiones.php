<?php
// Sesiones abiertas de todos, con las apps a las que se entró y el cierre.
?>
<div class="cab">
  <div>
    <h1>Sesiones</h1>
    <p class="cab-sub">Sesiones abiertas de ITHub. Cerrar una la cierra también en todas las aplicaciones a las que se entró con ella.</p>
  </div>
</div>

<div class="tabla-caja">
<table class="tabla">
  <thead>
    <tr><th>Persona</th><th>Dispositivo</th><th>Desde</th><th>Última actividad</th><th>Aplicaciones</th><th><span class="sr">Acciones</span></th></tr>
  </thead>
  <tbody>
  <?php if (!$sesiones): ?>
    <tr><td colspan="6" class="vacio">No hay sesiones abiertas.</td></tr>
  <?php endif; ?>
  <?php foreach ($sesiones as $s): ?>
    <tr class="<?= $s['inactiva'] ? 'fila-off' : '' ?>">
      <td data-th="Persona">
        <span class="persona">
          <?php if (!empty($s['avatar_url'])): ?>
            <img class="avatar" src="<?= e($s['avatar_url']) ?>" alt="" width="34" height="34" referrerpolicy="no-referrer">
          <?php else: ?>
            <span class="avatar avatar-ini"><?= e(iniciales($s['nombre'])) ?></span>
          <?php endif; ?>
          <span><strong><?= e($s['nombre']) ?></strong><span class="muted"><?= e($s['email']) ?></span></span>
        </span>
      </td>
      <td data-th="Dispositivo"><?= e(navegador_corto($s['user_agent'])) ?><span class="muted d-block">IP <?= e($s['ip'] ?? '—') ?></span></td>
      <td data-th="Desde"><?= e(fecha_legible($s['creada_en'])) ?></td>
      <td data-th="Última actividad">
        <?= e(hace($s['ultima_actividad'])) ?>
        <?php if ($s['inactiva']): ?><span class="badge badge-suave">Inactiva</span><?php elseif ($s['id'] === $yo['sid']): ?><span class="badge badge-ok">Vos</span><?php endif; ?>
      </td>
      <td data-th="Aplicaciones">
        <?php if (!$s['apps']): ?><span class="muted">Sólo el panel</span><?php endif; ?>
        <span class="chips">
        <?php foreach ($s['apps'] as $a): ?>
          <span class="chip" title="<?= e($a['nombre'] . ' · ' . $a['rol']) ?>"><span class="mini-ico mini-ico-sm c-<?= e($a['color']) ?>"><?= icono($a['icono']) ?></span><?= e($a['nombre']) ?></span>
        <?php endforeach; ?>
        </span>
      </td>
      <td class="acciones">
        <?php if ($s['editable']): ?>
        <form method="post" action="<?= e(url('sesion-cerrar')) ?>" data-confirmar="¿Cerrar la sesión de <?= e($s['nombre']) ?>? Se cierra también en las aplicaciones.">
          <?= csrf_campo() ?><input type="hidden" name="sid" value="<?= e($s['id']) ?>"><input type="hidden" name="volver" value="sesiones">
          <button class="btn btn-sec btn-sm" type="submit">Cerrar</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
