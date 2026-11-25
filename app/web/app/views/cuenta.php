<?php
// Mi cuenta: datos de Google, apps con su rol y sesiones abiertas.
?>
<div class="cab">
  <div>
    <span class="eyebrow">Tu perfil</span>
    <h1>Mi cuenta</h1>
    <p class="cab-sub">Tus datos vienen de Google Workspace: el nombre y la foto se actualizan solos cada vez que entrás.</p>
  </div>
</div>

<div class="columnas">
  <section class="panel-caja">
    <div class="perfil">
      <?php if (!empty($yo['avatar_url'])): ?>
        <img class="avatar avatar-xl" src="<?= e($yo['avatar_url']) ?>" alt="" width="72" height="72" referrerpolicy="no-referrer">
      <?php else: ?>
        <span class="avatar avatar-xl avatar-ini"><?= e(iniciales($yo['nombre'])) ?></span>
      <?php endif; ?>
      <div>
        <h2><?= e($yo['nombre']) ?></h2>
        <p class="muted"><?= e($yo['email']) ?></p>
        <span class="badge badge-<?= e($yo['rol']) ?>"><?= e(ROLES_PORTAL[$yo['rol']]) ?></span>
      </div>
    </div>

    <h3 class="caja-tit">Mis aplicaciones</h3>
    <?php if (!$apps): ?>
      <p class="muted">Todavía no tenés aplicaciones asignadas.</p>
    <?php else: ?>
      <ul class="lista-apps">
        <?php foreach ($apps as $a): ?>
          <li>
            <span class="mini-ico c-<?= e($a['color']) ?>"><?= icono($a['icono']) ?></span>
            <span class="lista-apps-nombre"><?= e($a['nombre']) ?></span>
            <span class="badge badge-rol"><?= e(ucfirst($a['mi_rol'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel-caja">
    <div class="caja-cab">
      <h3 class="caja-tit">Sesiones abiertas</h3>
      <?php if (count($sesiones) > 1): ?>
      <form method="post" action="<?= e(url('sesion-cerrar')) ?>" data-confirmar="¿Cerrar todas tus otras sesiones? Se cierran también en las aplicaciones.">
        <?= csrf_campo() ?><input type="hidden" name="todas" value="1">
        <button class="btn btn-sec btn-sm" type="submit">Cerrar las demás</button>
      </form>
      <?php endif; ?>
    </div>
    <ul class="sesiones">
      <?php foreach ($sesiones as $s): $actual = $s['id'] === $yo['sid']; ?>
      <li class="<?= $actual ? 'sesion-actual' : '' ?>">
        <span class="sesion-ico"><?= icono('reloj') ?></span>
        <span class="sesion-txt">
          <strong><?= e(navegador_corto($s['user_agent'])) ?></strong>
          <span class="muted">IP <?= e($s['ip'] ?? '—') ?> · desde <?= e(fecha_legible($s['creada_en'])) ?> · activa <?= e(hace($s['ultima_actividad'])) ?></span>
          <span class="muted"><?= (int)$s['apps'] ?> <?= (int)$s['apps'] === 1 ? 'aplicación' : 'aplicaciones' ?> con esta sesión</span>
        </span>
        <?php if ($actual): ?>
          <span class="badge badge-ok">Esta sesión</span>
        <?php else: ?>
          <form method="post" action="<?= e(url('sesion-cerrar')) ?>">
            <?= csrf_campo() ?><input type="hidden" name="sid" value="<?= e($s['id']) ?>">
            <button class="btn btn-sec btn-sm" type="submit">Cerrar</button>
          </form>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
