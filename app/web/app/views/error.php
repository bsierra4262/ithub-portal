<?php
// Pantalla de error genérica (404, 403, formulario vencido, pedido inválido).
?>
<section class="estado-pantalla">
  <span class="estado-ico"><?= icono('alerta') ?></span>
  <h1><?= e($titulo ?? 'Algo salió mal') ?></h1>
  <p class="muted"><?= e($mensaje ?? '') ?></p>
  <?php if (!empty($detalle)): ?><p class="muted"><code><?= e($detalle) ?></code></p><?php endif; ?>
  <a class="btn" href="<?= e(url('panel')) ?>">Ir al panel</a>
</section>
