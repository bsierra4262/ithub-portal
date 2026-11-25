<?php
// Panel: saludo, resumen (admin), filtros por categoría y las tarjetas.
$h = (int)date('G');
$saludo = $h < 6 ? 'Buenas noches' : ($h < 13 ? 'Buen día' : ($h < 20 ? 'Buenas tardes' : 'Buenas noches'));
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$hoy = $dias[(int)date('w')] . ' ' . date('j') . ' de ' . $meses[(int)date('n')];
?>
<section class="hero">
  <div>
    <p class="hero-fecha"><?= e(ucfirst($hoy)) ?></p>
    <h1><?= e($saludo) ?>, <?= e(primer_nombre($yo['nombre'])) ?></h1>
    <p class="hero-sub">
      <?php if ($total === 0): ?>
        Todavía no tenés aplicaciones asignadas. Pedíle acceso a un administrador de ITHub.
      <?php else: ?>
        Tenés acceso a <strong><?= $total ?></strong> <?= $total === 1 ? 'aplicación' : 'aplicaciones' ?>. Entrás a todas con esta misma sesión.
      <?php endif; ?>
    </p>
  </div>
</section>

<?php if ($resumen !== null): ?>
<section class="stats" aria-label="Resumen">
  <a class="stat" href="<?= e(url('usuarios')) ?>"><span class="stat-ico"><?= icono('usuarios') ?></span><span><span class="stat-n"><?= $resumen['usuarios'] ?></span><span class="stat-l">usuarios activos</span></span></a>
  <a class="stat" href="<?= e(url('sesiones')) ?>"><span class="stat-ico"><?= icono('reloj') ?></span><span><span class="stat-n"><?= $resumen['sesiones'] ?></span><span class="stat-l">sesiones abiertas</span></span></a>
  <a class="stat" href="<?= e(url('auditoria')) ?>"><span class="stat-ico"><?= icono('lista') ?></span><span><span class="stat-n"><?= $resumen['ingresos'] ?></span><span class="stat-l">ingresos hoy</span></span></a>
  <?php $tag = es_superadmin($yo) ? 'a' : 'div'; ?>
  <<?= $tag ?> class="stat"<?= $tag === 'a' ? ' href="' . e(url('aplicaciones')) . '"' : '' ?>><span class="stat-ico"><?= icono('apps') ?></span><span><span class="stat-n"><?= $resumen['apps'] ?></span><span class="stat-l">aplicaciones activas</span></span></<?= $tag ?>>
</section>
<?php endif; ?>

<?php if ($apps): ?>
<div class="barra-apps">
  <div class="categorias" role="group" aria-label="Filtrar por categoría">
    <button type="button" class="cat activo" data-categoria="" aria-pressed="true">Todas <span><?= count($apps) ?></span></button>
    <?php foreach ($categorias as $c => $n): ?>
      <button type="button" class="cat" data-categoria="<?= e($c) ?>" aria-pressed="false"><?= e($c) ?> <span><?= $n ?></span></button>
    <?php endforeach; ?>
  </div>
  <label class="buscador">
    <?= icono('buscar') ?>
    <span class="sr">Buscar aplicación</span>
    <input type="search" name="buscar" placeholder="Buscar aplicación" data-filtro-apps autocomplete="off">
    <kbd>/</kbd>
  </label>
</div>

<div class="tarjetas">
  <?php foreach ($apps as $a):
    $habilitada = $a['mi_rol'] !== null;
    $texto = mb_strtolower($a['nombre'] . ' ' . $a['descripcion'] . ' ' . $a['categoria']);
    $tag = $habilitada ? 'a' : 'div';
  ?>
  <<?= $tag ?> class="tarjeta<?= $habilitada ? '' : ' tarjeta-off' ?>"<?= $habilitada ? ' href="' . e(url('ir', ['app' => $a['clave']])) . '"' : ' aria-disabled="true"' ?>
     data-app="<?= e($texto) ?>" data-cat="<?= e($a['categoria']) ?>">
    <span class="tarjeta-cab">
      <span class="tarjeta-ico c-<?= e($a['color']) ?>"><?= icono($a['icono'], 'ico ico-app') ?></span>
      <span class="tarjeta-cat"><?= e($a['categoria']) ?></span>
    </span>
    <span class="tarjeta-txt">
      <span class="tarjeta-nombre"><?= e($a['nombre']) ?></span>
      <span class="tarjeta-desc"><?= e($a['descripcion']) ?></span>
    </span>
    <span class="tarjeta-pie">
      <?php if ($a['estado'] === 'proximamente'): ?>
        <span class="badge badge-suave">Próximamente</span>
      <?php elseif ($a['estado'] === 'mantenimiento'): ?>
        <span class="badge badge-warn">En mantenimiento</span>
      <?php else: ?>
        <span class="badge badge-rol" title="Tu rol en <?= e($a['nombre']) ?>"><?= e(ucfirst((string)$a['mi_rol'])) ?></span>
      <?php endif; ?>
      <?php if ($habilitada): ?><span class="tarjeta-ir">Abrir <?= icono('flecha') ?></span><?php endif; ?>
    </span>
  </<?= $tag ?>>
  <?php endforeach; ?>
</div>
<p class="vacio" data-sin-resultados hidden>No hay aplicaciones que coincidan con la búsqueda.</p>
<?php endif; ?>
