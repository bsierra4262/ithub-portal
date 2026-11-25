<?php
// ============================================================
//  Layout de todas las pantallas.
//    layout 'app'  → barra superior con navegación y menú de usuario
//    layout 'auth' → pantalla partida del login (sin navegación)
//  Recibe $contenido (la vista ya renderizada), $titulo, $yo y $__vista.
// ============================================================
$yo = $yo ?? null;
$titulo = $titulo ?? 'ITHub';
$avisos = flashes();
$nav = [];
if ($yo !== null) {
    $nav[] = ['panel', 'Panel', 'inicio', ['panel']];
    if (es_admin($yo)) {
        $nav[] = ['usuarios', 'Usuarios', 'usuarios', ['usuarios', 'usuario-form']];
        $nav[] = ['sesiones', 'Sesiones', 'reloj', ['sesiones']];
        $nav[] = ['auditoria', 'Auditoría', 'lista', ['auditoria']];
    }
    if (es_superadmin($yo)) {
        $nav[] = ['aplicaciones', 'Aplicaciones', 'apps', ['aplicaciones', 'aplicacion-form']];
    }
}
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#663399">
<title><?= e($titulo) ?> · ITHub</title>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=<?= e(ASSETS_V) ?>">
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= e(ASSETS_V) ?>" defer></script>
</head>
<?php if (($layout ?? 'app') === 'auth'): ?>
<body class="auth">
<?= $contenido ?>
</body>
<?php else: ?>
<body class="app">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<header class="topbar">
  <div class="topbar-in">
    <a class="marca" href="<?= e(url('panel')) ?>" aria-label="ITHub, ir al panel">
      <img src="<?= e(url('assets/img/ITH-logo-80x80.png')) ?>" alt="" width="34" height="34">
      <span class="marca-txt">IT<b>Hub</b></span>
    </a>
    <?php if ($yo !== null): ?>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-principal" data-nav-toggle>
      <?= icono('menu') ?><span class="sr">Menú</span>
    </button>
    <nav class="nav" id="nav-principal" aria-label="Principal">
      <?php foreach ($nav as [$ruta, $txt, $ico, $activas]): ?>
        <a href="<?= e(url($ruta)) ?>" class="nav-item<?= in_array($__vista, $activas, true) ? ' activo' : '' ?>"
           <?= in_array($__vista, $activas, true) ? 'aria-current="page"' : '' ?>><?= icono($ico) ?><span><?= e($txt) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <details class="usuario-menu">
      <summary aria-label="Mi cuenta">
        <?php if (!empty($yo['avatar_url'])): ?>
          <img class="avatar" src="<?= e($yo['avatar_url']) ?>" alt="" width="34" height="34" referrerpolicy="no-referrer">
        <?php else: ?>
          <span class="avatar avatar-ini"><?= e(iniciales($yo['nombre'])) ?></span>
        <?php endif; ?>
        <span class="usuario-nombre"><?= e(primer_nombre($yo['nombre'])) ?></span>
      </summary>
      <div class="usuario-pop">
        <div class="usuario-pop-cab">
          <strong><?= e($yo['nombre']) ?></strong>
          <span><?= e($yo['email']) ?></span>
          <span class="badge badge-<?= e($yo['rol']) ?>"><?= e(ROLES_PORTAL[$yo['rol']] ?? $yo['rol']) ?></span>
        </div>
        <a href="<?= e(url('cuenta')) ?>"><?= icono('usuario') ?> Mi cuenta</a>
        <form method="post" action="<?= e(url('salir')) ?>">
          <?= csrf_campo() ?>
          <button type="submit"><?= icono('salir') ?> Cerrar sesión</button>
        </form>
      </div>
    </details>
    <?php endif; ?>
  </div>
</header>
<main class="main" id="contenido">
  <?php foreach ($avisos as $a): ?>
    <div class="aviso aviso-<?= e($a['tipo']) ?>" role="status"><?= icono($a['tipo'] === 'error' ? 'alerta' : 'check') ?><span><?= e($a['mensaje']) ?></span></div>
  <?php endforeach; ?>
  <?= $contenido ?>
</main>
<footer class="pie">
  <span>ITHub · <?= e(cfg('app.org', 'INTELLIHELP')) ?></span>
  <span>Una sola sesión para todas las aplicaciones</span>
</footer>
</body>
<?php endif; ?>
</html>
