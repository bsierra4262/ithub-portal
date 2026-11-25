<?php
// Catálogo de aplicaciones.
?>
<div class="cab">
  <div>
    <h1>Aplicaciones</h1>
    <p class="cab-sub">Las tarjetas del panel y su conexión al ingreso único. Una app sin ingreso único igual se abre desde el panel, pero pide su propio login.</p>
  </div>
  <a class="btn" href="<?= e(url('aplicacion-form')) ?>"><?= icono('mas') ?> Nueva aplicación</a>
</div>

<div class="tabla-caja">
<table class="tabla">
  <thead><tr><th>Aplicación</th><th>Categoría</th><th>Roles</th><th>Con acceso</th><th>Ingreso único</th><th>Estado</th><th><span class="sr">Acciones</span></th></tr></thead>
  <tbody>
  <?php foreach ($apps as $a): ?>
    <tr class="<?= $a['estado'] === 'activa' ? '' : 'fila-off' ?>">
      <td data-th="Aplicación">
        <span class="persona">
          <span class="mini-ico c-<?= e($a['color']) ?>"><?= icono($a['icono']) ?></span>
          <span><strong><?= e($a['nombre']) ?></strong><span class="muted"><?= e($a['clave']) ?></span></span>
        </span>
      </td>
      <td data-th="Categoría"><?= e($a['categoria']) ?></td>
      <td data-th="Roles"><span class="chips"><?php foreach ($a['roles'] as $r): ?><span class="chip"><?= e($r) ?></span><?php endforeach; ?></span></td>
      <td data-th="Con acceso"><?= (int)($con_acceso[$a['id']] ?? 0) ?></td>
      <td data-th="Ingreso único">
        <?php if ($a['sso']): ?><span class="badge badge-ok"><?= icono('check') ?> Conectada</span>
        <?php else: ?><span class="badge badge-suave">Pendiente</span><?php endif; ?>
      </td>
      <td data-th="Estado"><span class="badge <?= $a['estado'] === 'activa' ? 'badge-ok' : ($a['estado'] === 'mantenimiento' ? 'badge-warn' : 'badge-suave') ?>"><?= e(ESTADOS_APP[$a['estado']] ?? $a['estado']) ?></span></td>
      <td class="acciones"><a class="btn btn-sec btn-sm" href="<?= e(url('aplicacion-form', ['id' => $a['id']])) ?>"><?= icono('editar') ?> Editar</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
