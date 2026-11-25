/* ============================================================
   ITHub — comportamiento de la interfaz (sin dependencias).

   · Menú del celular (botón [data-nav-toggle] → .nav-abierto en el body).
   · Panel: filtro por categoría (.cat) y buscador ([data-filtro-apps]),
     combinados.
   · Confirmaciones: un <form data-confirmar="..."> pregunta antes de enviar.
   · Copiar el secreto de una app ([data-copiar] → [data-copiable]).
   · Matriz de permisos: resalta la fila con acceso y cambia la ayuda según
     el rol del portal elegido.
   · Cierra el menú de usuario al hacer clic afuera o con Escape.
   La política de seguridad no permite JavaScript en línea: todo va acá.
   ============================================================ */
(function () {
  'use strict';

  var body = document.body;

  // ---- Menú del celular ----
  var toggle = document.querySelector('[data-nav-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var abierto = body.classList.toggle('nav-abierto');
      toggle.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
  }

  // ---- Menú de usuario: cerrar al hacer clic afuera o con Escape ----
  var menu = document.querySelector('.usuario-menu');
  if (menu) {
    document.addEventListener('click', function (ev) {
      if (menu.open && !menu.contains(ev.target)) { menu.open = false; }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
    });
  }

  // ---- Panel: categorías + buscador (se combinan) ----
  var filtro = document.querySelector('[data-filtro-apps]');
  var cats = document.querySelectorAll('.cat[data-categoria]');
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
          o.classList.toggle('activo', activo);
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
})();
