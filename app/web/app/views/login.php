<?php
// Login: panel de marca a la izquierda, ingreso con Google a la derecha.
$muestra = ['factura', 'crm', 'camion', 'caja', 'personas', 'ticket', 'salvavidas', 'libro', 'tareas'];
?>
<div class="auth-wrap">
  <section class="auth-marca" aria-hidden="true">
    <div class="auth-marca-in">
      <div class="auth-logo">
        <img src="<?= e(url('assets/img/ITH-logo-white.png')) ?>" alt="" width="64">
        <span class="marca-txt">IT<b>Hub</b></span>
      </div>
      <p class="auth-lema">Todas las aplicaciones de Intellihelp.<br>Una sola sesión.</p>
      <ul class="auth-mosaico">
        <?php foreach ($muestra as $i): ?><li><?= icono($i) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="auth-form">
    <div class="auth-card">
      <h1>Ingresar a ITHub</h1>
      <?php if ($pendiente && $app_pendiente): ?>
        <p class="auth-sub">Para entrar a <strong><?= e($app_pendiente) ?></strong>, primero ingresá con tu cuenta de Intellihelp.</p>
      <?php else: ?>
        <p class="auth-sub">Usá tu cuenta de Google <strong>@<?= e(cfg('google.allowed_domain', '')) ?></strong>.</p>
      <?php endif; ?>

      <?php if ($salio): ?>
        <div class="aviso aviso-ok" role="status"><?= icono('check') ?><span>Cerraste sesión en ITHub y en todas las aplicaciones.</span></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="aviso aviso-error" role="alert"><?= icono('alerta') ?><span><?= e($error) ?></span></div>
      <?php endif; ?>

      <?php if ($habilitado): ?>
        <a class="btn-google" href="<?= e(url('google-login')) ?>"><?= icono_google() ?><span>Continuar con Google</span></a>
      <?php else: ?>
        <div class="aviso aviso-error" role="alert"><?= icono('alerta') ?><span>El ingreso con Google no está configurado.</span></div>
      <?php endif; ?>

      <p class="auth-nota"><?= icono('candado') ?>Sin contraseñas: el acceso es sólo con Google Workspace y cada aplicación respeta los permisos que te asignen en ITHub.</p>
    </div>
    <p class="auth-pie">INTELLIHELP · ITHub</p>
  </section>
</div>
