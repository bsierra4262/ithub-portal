<?php
// Sin permiso para una aplicación (desde el panel o desde /oauth/authorize).
?>
<section class="estado-pantalla">
  <span class="estado-ico"><?= icono('candado') ?></span>
  <h1>No tenés acceso<?= $app ? ' a ' . e($app['nombre']) : '' ?></h1>
  <p class="muted">
    <?php if ($app && $app['estado'] === 'mantenimiento'): ?>
      La aplicación está en mantenimiento. Probá de nuevo más tarde.
    <?php elseif ($app && $app['estado'] === 'proximamente'): ?>
      La aplicación todavía no está disponible.
    <?php else: ?>
      Tu usuario no tiene permiso para esta aplicación. Si lo necesitás, pedíselo a un administrador de ITHub.
    <?php endif; ?>
  </p>
  <a class="btn" href="<?= e(url('panel')) ?>">Volver al panel</a>
</section>
