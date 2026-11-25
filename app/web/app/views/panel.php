<?php
// Panel: banner de bienvenida (como RRHH), resumen para administradores y las
// tarjetas de las aplicaciones a las que la persona tiene acceso.
// También lo usa /vista-previa ($previa = true): el panel tal como lo ve otra
// persona, con las tarjetas sin enlace.
$previa = $previa ?? false;
$persona = $persona ?? $yo;
$h = (int)date('G');
$saludo = $h < 6 ? 'Buenas noches' : ($h < 13 ? 'Buen día' : ($h < 20 ? 'Buenas tardes' : 'Buenas noches'));
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$hoy = $dias[(int)date('w')] . ' ' . date('j') . ' de ' . $meses[(int)date('n')] . ' de ' . date('Y');
?>
<?php if ($previa): ?>
<div class="previa-barra" role="status">
  <span class="previa-ico"><?= icono('ojo') ?></span>
  <span class="grow">Estás viendo el panel <strong>como lo ve <?= e($persona['nombre']) ?></strong> (<?= e($persona['email']) ?>, <?= e(ROLES_PORTAL[$persona['rol']] ?? $persona['rol']) ?>). Sólo aparecen las aplicaciones que tiene habilitadas.</span>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('usuario-form', ['id' => $persona['id']])) ?>"><?= icono('editar') ?> Editar permisos</a>
  <a class="btn btn-primary btn-sm" href="<?= e(url('permisos')) ?>">Volver a Permisos</a>
</div>
<?php endif; ?>

<section class="hero">
  <div class="hero-txt">
    <span class="eyebrow eyebrow-claro"><?= e(ucfirst($hoy)) ?></span>
    <h1><?= $previa ? 'Panel de ' . e(primer_nombre($persona['nombre'])) : e($saludo) . ', ' . e(primer_nombre($persona['nombre'])) ?></h1>
    <p>
      <?php if ($total === 0): ?>
        <?= $previa ? 'No tiene aplicaciones asignadas: su panel aparece vacío.' : 'Todavía no tenés aplicaciones asignadas. Pedíle acceso a un administrador de ITHub.' ?>
      <?php else: ?>
        <?= $previa ? 'Tiene' : 'Tenés' ?> acceso a <strong><?= $total ?></strong> <?= $total === 1 ? 'aplicación' : 'aplicaciones' ?> con una sola sesión.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($resumen !== null && !$previa): ?>
  <div class="quick" aria-label="Resumen">
    <a class="quick-btn" href="<?= e(url('usuarios')) ?>"><span><?= icono('usuarios') ?></span><b><?= $resumen['usuarios'] ?></b><small>usuarios activos</small></a>
    <a class="quick-btn" href="<?= e(url('sesiones')) ?>"><span><?= icono('reloj') ?></span><b><?= $resumen['sesiones'] ?></b><small>sesiones abiertas</small></a>
    <a class="quick-btn" href="<?= e(url('auditoria')) ?>"><span><?= icono('lista') ?></span><b><?= $resumen['ingresos'] ?></b><small>ingresos hoy</small></a>
    <a class="quick-btn" href="<?= e(url('permisos')) ?>"><span><?= icono('llave') ?></span><b><?= $resumen['apps'] ?></b><small>aplicaciones activas</small></a>
  </div>
  <?php endif; ?>
</section>

<?php if ($apps): ?>
<section class="card">
  <div class="card-head card-head-wrap">
    <h2><?= icono('apps') ?> <?= $previa ? 'Sus aplicaciones' : 'Tus aplicaciones' ?> <em class="count"><?= $total ?></em></h2>
    <label class="buscador">
      <?= icono('buscar') ?>
      <span class="sr">Buscar aplicación</span>
      <input type="search" name="buscar" placeholder="Buscar aplicación" data-filtro-apps autocomplete="off">
      <kbd>/</kbd>
    </label>
  </div>
  <?php if (count($categorias) > 1): ?>
  <div class="chips categorias" role="group" aria-label="Filtrar por categoría">
    <button type="button" class="chip on" data-categoria="" aria-pressed="true">Todas <span><?= count($apps) ?></span></button>
    <?php foreach ($categorias as $c => $n): ?>
      <button type="button" class="chip" data-categoria="<?= e($c) ?>" aria-pressed="false"><?= e($c) ?> <span><?= $n ?></span></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="tarjetas">
    <?php foreach ($apps as $a):
      $habilitada = $a['mi_rol'] !== null;
      $texto = mb_strtolower($a['nombre'] . ' ' . $a['descripcion'] . ' ' . $a['categoria']);
      $enlace = $habilitada && !$previa;
      $tag = $enlace ? 'a' : 'div';
    ?>
    <<?= $tag ?> class="tarjeta<?= $habilitada ? '' : ' tarjeta-off' ?>"<?= $enlace ? ' href="' . e(url('ir', ['app' => $a['clave']])) . '"' : '' ?>
       data-app="<?= e($texto) ?>" data-cat="<?= e($a['categoria']) ?>">
      <span class="tarjeta-cab">
        <span class="tarjeta-ico c-<?= e($a['color']) ?>"><?= icono($a['icono'], 'ico ico-app') ?></span>
        <span class="tarjeta-cat"><?= e($a['categoria']) ?></span>
      </span>
      <span class="tarjeta-nombre"><?= e($a['nombre']) ?></span>
      <span class="tarjeta-desc"><?= e($a['descripcion']) ?></span>
      <span class="tarjeta-pie">
        <?php if ($a['estado'] === 'proximamente'): ?>
          <span class="badge badge-suave">Próximamente</span>
        <?php elseif ($a['estado'] === 'mantenimiento'): ?>
          <span class="badge badge-warn">En mantenimiento</span>
        <?php else: ?>
          <span class="badge badge-rol" title="Rol en <?= e($a['nombre']) ?>"><?= icono('usuario') ?><?= e(ucfirst((string)$a['mi_rol'])) ?></span>
        <?php endif; ?>
        <?php if ($enlace): ?><span class="tarjeta-ir">Abrir <?= icono('flecha') ?></span><?php endif; ?>
      </span>
    </<?= $tag ?>>
    <?php endforeach; ?>
  </div>
  <p class="vacio" data-sin-resultados hidden>No hay aplicaciones que coincidan con la búsqueda.</p>
</section>
<?php else: ?>
<section class="card vacio-grande">
  <span class="estado-ico"><?= icono('candado') ?></span>
  <h2><?= $previa ? 'Sin aplicaciones habilitadas' : 'Todavía no tenés aplicaciones' ?></h2>
  <p class="muted"><?= $previa ? 'Asignale un rol en las aplicaciones que necesite desde «Editar permisos».' : 'Cuando un administrador te habilite una aplicación, va a aparecer acá.' ?></p>
</section>
<?php endif; ?>
