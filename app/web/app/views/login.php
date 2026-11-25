<?php
// Login: panel de marca a la izquierda (como el Portal de RRHH), ingreso con
// Google a la derecha. No hay usuario y contraseña: sólo Google Workspace.
$dominio = (string)cfg('google.allowed_domain', '');
?>
<div class="auth">
  <section class="auth-hero">
    <header class="auth-brand">
      <img src="<?= e(url('assets/img/logo-ith-mark.png')) ?>" alt="ITH" class="auth-logo" width="64" height="36">
      <span>Intellihelp<small>ITHub</small></span>
    </header>

    <div class="auth-copy">
      <span class="auth-eyebrow">Portal de aplicaciones</span>
      <h1>Todas tus herramientas,<br><span>una sola sesión</span></h1>
      <p>Facturación, CRM, inventario, remitos, recursos humanos y el resto de los sistemas de Intellihelp en un mismo lugar. Entrás una vez con tu cuenta de Google y cada aplicación te reconoce con el rol que tenés asignado.</p>

      <ul class="auth-features">
        <li>
          <span class="af-ic"><?= icono('escudo') ?></span>
          <div><b>Ingreso único con Google Workspace</b><small>Sin contraseñas nuevas: el acceso es sólo con tu cuenta @<?= e($dominio) ?>.</small></div>
        </li>
        <li>
          <span class="af-ic"><?= icono('llave') ?></span>
          <div><b>Permisos por aplicación</b><small>Cada persona ve únicamente las aplicaciones que tiene habilitadas, con su rol en cada una.</small></div>
        </li>
        <li>
          <span class="af-ic"><?= icono('reloj') ?></span>
          <div><b>Control de sesiones</b><small>Cerrar sesión en ITHub la cierra en todas las aplicaciones, y queda registrado.</small></div>
        </li>
      </ul>
    </div>

    <svg class="auth-waves" viewBox="0 0 220 160" aria-hidden="true"><g fill="none" stroke="#ffffff" stroke-width="6" stroke-linecap="round"><path d="M10 70a140 140 0 0 1 200 0"/><path d="M45 105a90 90 0 0 1 130 0"/><path d="M80 138a40 40 0 0 1 60 0"/></g></svg>
    <footer class="auth-foot">
      <span><?= icono('candado') ?> Conexión cifrada · Acceso exclusivo para colaboradores</span>
      <span>© <?= date('Y') ?> Intellihelp</span>
    </footer>
  </section>

  <section class="auth-panel">
    <div class="auth-card">
      <h2>Iniciar sesión</h2>
      <?php if ($pendiente && $app_pendiente): ?>
        <p class="auth-sub">Para entrar a <strong><?= e($app_pendiente) ?></strong>, primero ingresá con tu cuenta de Intellihelp.</p>
      <?php else: ?>
        <p class="auth-sub">Ingresá con tu cuenta corporativa de Google.</p>
      <?php endif; ?>

      <?php if ($salio): ?>
        <div class="aviso aviso-ok" role="status"><?= icono('check') ?><span>Cerraste sesión en ITHub y en todas las aplicaciones.</span></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="aviso aviso-error" role="alert"><?= icono('alerta') ?><span><?= e($error) ?></span></div>
      <?php endif; ?>

      <?php if ($habilitado): ?>
        <a class="btn-google" href="<?= e(url('google-login')) ?>"><?= icono_google() ?><span>Continuar con Google</span></a>
        <p class="auth-google-note">Para cuentas @<?= e($dominio) ?></p>
      <?php else: ?>
        <div class="aviso aviso-error" role="alert"><?= icono('alerta') ?><span>El ingreso con Google no está configurado.</span></div>
      <?php endif; ?>

      <p class="auth-help">¿No ves una aplicación o no podés entrar? Pedíselo al administrador de ITHub de tu área.</p>
    </div>
  </section>
</div>
