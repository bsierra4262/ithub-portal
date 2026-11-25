<?php
declare(strict_types=1);

// ============================================================
//  Catálogo inicial de aplicaciones de Intellihelp (lo carga instalar.php
//  sólo si la tabla está vacía; después se edita desde la pantalla
//  Aplicaciones). Los roles van con los nombres que usa cada app.
//  Ninguna trae secreto ni URIs: el ingreso único se conecta app por app.
//  `orden` es el del panel completo (Facturación primero), no por categoría.
// ============================================================

return [
    // --- Gestión ---
    ['facturacion', 'Facturación', 'Facturas de venta, clientes, servicios y cobranzas.', 'Gestión', 10,
     'https://facturacion.intellihelp.tech', 'factura', 'violeta', ['admin', 'ventas', 'cobranzas']],
    ['remitos', 'Remitos y Comprobantes', 'Remitos, comprobantes y conformidades, integrado con ARCA.', 'Gestión', 40,
     'https://remitos.intellihelp.tech', 'camion', 'uva', ['admin', 'operador']],
    ['comprobantes', 'Rendición de Tickets', 'Carga de comprobantes con OCR y validación contra el padrón.', 'Gestión', 50,
     'https://intellihelp.tech/comprobantes/login', 'ticket', 'lila', ['admin', 'carga']],

    // --- Comercial ---
    ['crm', 'CRM', 'Contactos, embudo de ventas, campañas de correo y agenda.', 'Comercial', 20,
     'https://intellihelp.tech/crm/login', 'crm', 'violeta', ['admin', 'comercial']],

    // --- Operaciones ---
    ['inventario', 'Inventario ITH', 'Activos de IT de Intellihelp: historial, papelera y respaldo en Drive.', 'Operaciones', 30,
     'https://desarrollo.intellihelp.tech/auth/login.php?company=ITH', 'caja', 'uva', ['admin', 'tecnico', 'viewer']],
    ['inventario-superadmin', 'Inventario · SuperAdmin', 'Empresas, portales y configuración del inventario multiempresa.', 'Operaciones', 110,
     'https://desarrollo.intellihelp.tech/auth/superadmin_login.php', 'escudo', 'tinta', ['superadmin']],
    ['ayuda', 'Mesa de Ayuda', 'Tickets de soporte de clientes y equipo interno (GLPI).', 'Operaciones', 70,
     'https://ayuda.intellihelp.tech', 'salvavidas', 'lavanda', ['admin', 'tecnico', 'autoservicio']],
    ['tareas', 'Tareas', 'Tablero de tareas del equipo de Intellihelp.', 'Operaciones', 80,
     'https://tareas.intellihelp.tech', 'tareas', 'lila', ['admin', 'usuario']],
    ['ithos', 'ITHOS Gestor', 'Portero por voz, mesa de ayuda con IA y tablero del equipo.', 'Operaciones', 100,
     'https://ithos.intellihelp.tech', 'robot', 'tinta', ['admin', 'usuario']],

    // --- Personas ---
    ['rrhh', 'Capital Humano', 'Legajos, licencias, calendario y novedades de RRHH.', 'Personas', 60,
     'https://rrhh.intellihelp.tech', 'personas', 'crema', ['admin', 'hr', 'manager', 'employee']],

    // --- Conocimiento ---
    ['wiki', 'Wiki', 'Base de conocimiento de Intellihelp: procedimientos y documentación.', 'Conocimiento', 90,
     'https://wiki.intellihelp.tech', 'libro', 'lavanda', ['admin', 'miembro', 'lector']],
];
