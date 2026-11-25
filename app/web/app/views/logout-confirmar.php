<?php
// Confirmación de salida pedida por una app sin id_token_hint.
?>
<div class="auth-solo">
  <div class="auth-card">
    <span class="estado-ico"><?= icono('salir') ?></span>
    <h1>¿Cerrar sesión?</h1>
    <p class="auth-sub">
      <?= $app ? e($app['nombre']) . ' pide cerrar tu sesión.' : 'Se pidió cerrar tu sesión.' ?>
      Como la sesión es una sola, se cierra en ITHub y en todas las aplicaciones.
    </p>
    <form method="post" action="<?= e(url('oauth/logout')) ?>" class="auth-acciones">
      <?= csrf_campo() ?>
      <?php foreach ($campos as $k => $val): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($val) ?>"><?php endforeach; ?>
      <a class="btn btn-sec" href="<?= e(url('panel')) ?>">No, seguir</a>
      <button class="btn" type="submit">Sí, cerrar sesión</button>
    </form>
  </div>
</div>
