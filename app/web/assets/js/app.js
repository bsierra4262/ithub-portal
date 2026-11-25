/* ============================================================
   ITHub — comportamiento de la interfaz (sin dependencias).

   · Menú del celular (botón [data-nav-toggle] → .nav-open en el body; el
     velo [data-nav-cerrar] y Escape lo cierran).
   · Panel: filtro por categoría ([data-categoria]) y buscador
     ([data-filtro-apps]), combinados.
   · Confirmaciones: un <form data-confirmar="..."> pregunta antes de enviar.
   · Copiar el secreto de una app ([data-copiar] → [data-copiable]).
   · Matriz de permisos: resalta la fila con acceso y cambia la ayuda según
     el rol del portal elegido.
   · Permisos: cada celda de la matriz se pinta según el rol elegido, las
     cambiadas se marcan, el pie cuenta los cambios sin guardar y avisa si se
     sale de la página sin guardarlos.
   La política de seguridad no permite JavaScript en línea: todo va acá.
   ============================================================ */
(function () {
  'use strict';

  var body = document.body;

  // ---- Menú del celular: la barra lateral entra desde la izquierda ----
  var toggle = document.querySelector('[data-nav-toggle]');
  var cerrarNav = function () {
    body.classList.remove('nav-open');
    if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
  };
  if (toggle) {
    toggle.addEventListener('click', function () {
      var abierto = body.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    var velo = document.querySelector('[data-nav-cerrar]');
    if (velo) { velo.addEventListener('click', cerrarNav); }
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && body.classList.contains('nav-open')) { cerrarNav(); toggle.focus(); }
    });
  }

  // ---- Panel: categorías + buscador (se combinan) ----
  var filtro = document.querySelector('[data-filtro-apps]');
  var cats = document.querySelectorAll('[data-categoria]');
  var tarjetas = document.querySelectorAll('.tarjeta[data-app]');
  if (tarjetas.length) {
    var sinResultados = document.querySelector('[data-sin-resultados]');
    var categoria = '';
    var normalizar = function (s) {
      return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    };
    var aplicar = function () {
      var q = filtro ? normalizar(filtro.value.trim()) : '';
      var visibles = 0;
      tarjetas.forEach(function (t) {
        var ok = (categoria === '' || t.getAttribute('data-cat') === categoria)
          && (q === '' || normalizar(t.getAttribute('data-app')).indexOf(q) !== -1);
        t.hidden = !ok;
        if (ok) { visibles++; }
      });
      if (sinResultados) { sinResultados.hidden = visibles !== 0; }
    };
    cats.forEach(function (b) {
      b.addEventListener('click', function () {
        categoria = b.getAttribute('data-categoria');
        cats.forEach(function (o) {
          var activo = o === b;
          o.classList.toggle('on', activo);
          o.setAttribute('aria-pressed', activo ? 'true' : 'false');
        });
        aplicar();
      });
    });
    if (filtro) {
      filtro.addEventListener('input', aplicar);
      // "/" lleva al buscador, como en la mayoría de los paneles.
      document.addEventListener('keydown', function (ev) {
        if (ev.key === '/' && document.activeElement === document.body) { ev.preventDefault(); filtro.focus(); }
      });
    }
  }

  // ---- Confirmaciones ----
  document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirmar'))) { ev.preventDefault(); }
    });
  });

  // ---- Copiar el secreto ----
  document.querySelectorAll('[data-copiar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var cod = b.parentNode.querySelector('[data-copiable]');
      if (!cod || !navigator.clipboard) { return; }
      navigator.clipboard.writeText(cod.textContent.trim()).then(function () {
        var txt = b.textContent;
        b.textContent = 'Copiado';
        b.classList.add('copiado');
        setTimeout(function () { b.textContent = txt; b.classList.remove('copiado'); }, 1800);
      });
    });
  });

  // ---- Matriz de permisos ----
  document.querySelectorAll('[data-permiso]').forEach(function (s) {
    s.addEventListener('change', function () {
      s.closest('.permiso').classList.toggle('permiso-on', s.value !== '');
    });
  });
  var rolPortal = document.querySelector('[data-rol-portal]');
  if (rolPortal) {
    rolPortal.addEventListener('change', function () {
      var esSuper = rolPortal.value === 'superadmin';
      var a = document.querySelector('[data-nota-super]');
      var b = document.querySelector('[data-nota-comun]');
      if (a) { a.hidden = !esSuper; }
      if (b) { b.hidden = esSuper; }
    });
  }

  // ---- Matriz de Permisos ----
  var matriz = document.querySelector('form[data-matriz]');
  if (matriz) {
    var celdas = matriz.querySelectorAll('select[data-celda]');
    var estado = matriz.querySelector('[data-matriz-estado]');
    var textoBase = estado ? estado.innerHTML : '';
    var pendientes = 0;
    var pintar = function (s) {
      var td = s.closest('td');
      var cambio = s.value !== s.getAttribute('data-inicial');
      td.classList.toggle('celda-on', s.value !== '');
      td.classList.toggle('celda-auto', s.value === '' && s.getAttribute('data-super') === '1');
      td.classList.toggle('celda-cambio', cambio);
      return cambio;
    };
    var contar = function () {
      pendientes = 0;
      celdas.forEach(function (s) { if (pintar(s)) { pendientes++; } });
      if (estado) {
        estado.innerHTML = pendientes === 0 ? textoBase
          : '<strong>' + pendientes + (pendientes === 1 ? ' cambio sin guardar' : ' cambios sin guardar') + '.</strong> Guardá para aplicarlos.';
      }
    };
    celdas.forEach(function (s) { s.addEventListener('change', contar); });
    var enviando = false;
    matriz.addEventListener('submit', function () { enviando = true; });
    window.addEventListener('beforeunload', function (ev) {
      if (pendientes > 0 && !enviando) { ev.preventDefault(); ev.returnValue = ''; }
    });
  }
})();
