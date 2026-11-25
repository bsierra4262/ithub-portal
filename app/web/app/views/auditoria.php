<?php
// Auditoría: filtros, tabla y paginado.
$qs = static fn(array $extra): string => url('auditoria', array_filter(array_merge($filtros, $extra), static fn($v) => $v !== '' && $v !== 0));

/** Detalle legible de un evento (sin mostrar el JSON crudo). */
$detalle = static function (?string $json): string {
    $d = $json ? json_decode($json, true) : null;
    if (!is_array($d)) {
        return '';
    }
    $partes = [];
    foreach ($d as $k => $v) {
        if (is_array($v) && isset($v['de'], $v['a'])) {
            $partes[] = "$k: {$v['de']} → {$v['a']}";
        } elseif (is_array($v)) {
            $partes[] = "$k: " . implode(', ', array_map('strval', $v));
        } else {
            $partes[] = str_replace('_', ' ', (string)$k) . ': ' . (is_bool($v) ? ($v ? 'sí' : 'no') : $v);
        }
    }
    return implode(' · ', $partes);
};
?>
<div class="cab">
  <div>
    <span class="eyebrow">Administración</span>
    <h1>Auditoría</h1>
    <p class="cab-sub">Ingresos, salidas, entradas a cada aplicación y cambios de usuarios, permisos y aplicaciones.</p>
  </div>
</div>

<form class="filtros" method="get" action="<?= e(url('auditoria')) ?>">
  <label><span class="sr">Persona</span>
    <select name="usuario">
      <option value="">Todas las personas</option>
      <?php foreach ($usuarios as $u): ?><option value="<?= (int)$u['id'] ?>"<?= $filtros['usuario'] === (int)$u['id'] ? ' selected' : '' ?>><?= e($u['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label><span class="sr">Acción</span>
    <select name="accion">
      <option value="">Todas las acciones</option>
      <?php foreach (ACCIONES_AUDITORIA as $k => $v): ?><option value="<?= e($k) ?>"<?= $filtros['accion'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label><span class="sr">Aplicación</span>
    <select name="app">
      <option value="">Todas las aplicaciones</option>
      <?php foreach ($apps as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $filtros['app'] === (int)$a['id'] ? ' selected' : '' ?>><?= e($a['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="fecha"><span>Desde</span><input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
  <label class="fecha"><span>Hasta</span><input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
  <button class="btn btn-sec" type="submit">Filtrar</button>
  <?php if (array_filter($filtros)): ?><a class="link" href="<?= e(url('auditoria')) ?>">Limpiar</a><?php endif; ?>
</form>

<div class="tabla-caja">
<table class="tabla">
  <thead><tr><th>Fecha</th><th>Persona</th><th>Acción</th><th>Sobre</th><th>Detalle</th><th>IP</th></tr></thead>
  <tbody>
  <?php if (!$eventos): ?>
    <tr><td colspan="6" class="vacio">No hay eventos con esos filtros.</td></tr>
  <?php endif; ?>
  <?php foreach ($eventos as $ev): ?>
    <tr>
      <td data-th="Fecha" class="nowrap"><?= e(fecha_legible($ev['creado_en'])) ?></td>
      <td data-th="Persona"><?= $ev['usuario_nombre'] !== null ? e($ev['usuario_nombre']) : '<span class="muted">—</span>' ?></td>
      <td data-th="Acción"><span class="accion accion-<?= e($ev['accion']) ?>"><?= e(ACCIONES_AUDITORIA[$ev['accion']] ?? $ev['accion']) ?></span></td>
      <td data-th="Sobre"><?= e($ev['app_nombre'] ?? '') ?><?= $ev['app_nombre'] && $ev['objetivo'] ? ' · ' : '' ?><span class="muted"><?= e($ev['objetivo'] ?? '') ?></span></td>
      <td data-th="Detalle" class="muted"><?= e($detalle($ev['detalle'])) ?></td>
      <td data-th="IP" class="muted nowrap"><?= e($ev['ip'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<nav class="paginas" aria-label="Páginas">
  <span class="muted"><?= number_format($total, 0, ',', '.') ?> eventos</span>
  <?php if ($paginas > 1): ?>
    <?php if ($pagina > 1): ?><a class="btn btn-sec btn-sm" href="<?= e($qs(['p' => $pagina - 1])) ?>">← Anteriores</a><?php endif; ?>
    <span>Página <?= $pagina ?> de <?= $paginas ?></span>
    <?php if ($pagina < $paginas): ?><a class="btn btn-sec btn-sm" href="<?= e($qs(['p' => $pagina + 1])) ?>">Siguientes →</a><?php endif; ?>
  <?php endif; ?>
</nav>
