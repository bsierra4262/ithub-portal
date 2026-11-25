<?php
// ============================================================
//  Layout de todas las pantallas (misma estructura que el Portal de RRHH).
//    layout 'app'  → barra lateral violeta + barra superior con el título
//    layout 'auth' → pantalla partida del login (sin navegación)
//  Recibe $contenido (la vista ya renderizada), $titulo, $yo y $__vista.
// ============================================================
$yo = $yo ?? null;
$titulo = $titulo ?? 'ITHub';
$avisos = flashes();
$secciones = [];
if ($yo !== null) {
    $secciones['General'] = [
        ['panel', 'Inicio', 'inicio', ['panel']],
        ['cuenta', 'Mi cuenta', 'usuario', ['cuenta']],
    ];
    if (es_admin($yo)) {
        $secciones['Administración'] = [
            ['usuarios', 'Usuarios', 'usuarios', ['usuarios', 'usuario-form']],
            ['permisos', 'Permisos', 'llave', ['permisos', 'vista-previa']],
            ['sesiones', 'Sesiones', 'reloj', ['sesiones']],
            ['auditoria', 'Auditoría', 'lista', ['auditoria']],
        ];
    }
    if (es_superadmin($yo)) {
        $secciones['Configuración'] = [
            ['aplicaciones', 'Aplicaciones', 'apps', ['aplicaciones', 'aplicacion-form']],
        ];
    }
}
$cssV = e(url('assets/css/app.css')) . '?v=' . e(ASSETS_V);
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#663399">
<title><?= e($titulo) ?> · ITHub · Intellihelp</title>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
<link rel="preload" href="<?= e(url('assets/fonts/saira-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= $cssV ?>">
<script src="<?= e(url('assets/js/app.js')) ?>?v=<?= e(ASSETS_V) ?>" defer></script>
</head>
<?php if (($layout ?? 'app') === 'auth'): ?>
<body class="auth-body">
<?= $contenido ?>
</body>
<?php else: ?>
<body class="app">
<a class="saltar" href="#contenido">Saltar al contenido</a>
<div class="shell">
  <?php if ($yo !== null): ?>
  <aside class="sidebar" id="barra-lateral" aria-label="Navegación">
    <a class="brand" href="<?= e(url('panel')) ?>" aria-label="ITHub, ir al inicio">
      <img src="<?= e(url('assets/img/logo-ith-mark.png')) ?>" alt="" width="46" height="26">
      <span><b>Intellihelp</b><small>ITHub</small></span>
    </a>
    <nav class="nav" aria-label="Principal">
      <?php foreach ($secciones as $sec => $items): ?>
        <?php if ($sec !== 'General'): ?><p class="nav-title"><?= e($sec) ?></p><?php endif; ?>
        <?php foreach ($items as [$ruta, $txt, $ico, $activas]): $on = in_array($nav_activa ?? $__vista, $activas, true); ?>
          <a href="<?= e(url($ruta)) ?>" class="<?= $on ? 'active' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= icono($ico) ?><span><?= e($txt) ?></span></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <a class="me" href="<?= e(url('cuenta')) ?>" title="Mi cuenta">
        <?php if (!empty($yo['avatar_url'])): ?>
          <img class="avatar" src="<?= e($yo['avatar_url']) ?>" alt="" width="40" height="40" referrerpolicy="no-referrer">
        <?php else: ?>
          <span class="avatar avatar-ini"><?= e(iniciales($yo['nombre'])) ?></span>
        <?php endif; ?>
        <span><b><?= e($yo['nombre']) ?></b><small><?= e(ROLES_PORTAL[$yo['rol']] ?? $yo['rol']) ?></small></span>
      </a>
      <form method="post" action="<?= e(url('salir')) ?>">
        <?= csrf_campo() ?>
        <button class="icon-btn" type="submit" title="Cerrar sesión" aria-label="Cerrar sesión"><?= icono('salir') ?></button>
      </form>
    </div>
  </aside>
  <div class="scrim" data-nav-cerrar></div>
  <?php endif; ?>

  <div class="main">
    <header class="topbar">
      <?php if ($yo !== null): ?>
      <button class="icon-btn menu-btn" type="button" aria-expanded="false" aria-controls="barra-lateral" data-nav-toggle>
        <?= icono('menu') ?><span class="sr">Menú</span>
      </button>
      <?php endif; ?>
      <span class="topbar-title"><?= e($titulo) ?></span>
      <span class="topbar-sesion"><?= icono('escudo') ?> Sesión única de Google Workspace</span>
    </header>
    <main class="content" id="contenido">
      <?php foreach ($avisos as $a): ?>
        <div class="aviso aviso-<?= e($a['tipo']) ?>" role="status"><?= icono($a['tipo'] === 'error' ? 'alerta' : 'check') ?><span><?= e($a['mensaje']) ?></span></div>
      <?php endforeach; ?>
      <?= $contenido ?>
    </main>
    <footer class="pie">
      <span>© <?= date('Y') ?> Intellihelp · ITHub</span>
      <span>Una sola sesión para todas las aplicaciones</span>
    </footer>
  </div>
</div>
</body>
<?php endif; ?>
</html>
