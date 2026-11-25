<?php
declare(strict_types=1);

// ============================================================
//  Íconos SVG en línea (trazo, 24×24, color del texto).
//
//  ICONOS_APP son los que se eligen para la tarjeta de una aplicación
//  (pantalla Aplicaciones); ICONOS_UI los de la interfaz. Todos se dibujan
//  con icono(): sin fuentes de íconos ni pedidos extra.
// ============================================================

const ICONOS_APP = [
    'app'        => ['Genérica',     '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
    'factura'    => ['Factura',      '<path d="M6 2.5h12v19l-3-2-3 2-3-2-3 2z"/><path d="M9 7.5h6M9 11.5h6M9 15.5h3.5"/>'],
    'crm'        => ['Clientes',     '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M17.5 14.6c2.2.2 3.7 1.5 4.1 3.9"/>'],
    'camion'     => ['Remitos',      '<path d="M2.5 5.5h11v11h-11z"/><path d="M13.5 9.5h4l3 3.5v3.5h-7"/><circle cx="6.5" cy="17.5" r="2"/><circle cx="17" cy="17.5" r="2"/>'],
    'caja'       => ['Inventario',   '<path d="M12 2.8l8.5 4.6v9.2L12 21.2l-8.5-4.6V7.4z"/><path d="M3.8 7.6L12 12l8.2-4.4M12 12v9"/>'],
    'escudo'     => ['Escudo',       '<path d="M12 2.8l7.5 3v5.7c0 4.6-3.1 8.2-7.5 9.7-4.4-1.5-7.5-5.1-7.5-9.7V5.8z"/><path d="M8.8 12l2.2 2.2 4.3-4.4"/>'],
    'personas'   => ['Personas',     '<circle cx="12" cy="7.5" r="3.5"/><path d="M5 20.5c.8-4 3.6-6.3 7-6.3s6.2 2.3 7 6.3"/>'],
    'ticket'     => ['Comprobante',  '<path d="M3.5 5.5h17v4a2.5 2.5 0 0 0 0 5v4h-17v-4a2.5 2.5 0 0 0 0-5z"/><path d="M14.5 6v2M14.5 11v2M14.5 16v2"/>'],
    'salvavidas' => ['Mesa de ayuda','<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.8"/><path d="M5.6 5.6l3.7 3.7M14.7 14.7l3.7 3.7M18.4 5.6l-3.7 3.7M9.3 14.7l-3.7 3.7"/>'],
    'libro'      => ['Wiki',         '<path d="M3.5 4.5H9a3 3 0 0 1 3 3v13a2.5 2.5 0 0 0-2.5-2.5H3.5z"/><path d="M20.5 4.5H15a3 3 0 0 0-3 3v13a2.5 2.5 0 0 1 2.5-2.5h6z"/>'],
    'tareas'     => ['Tareas',       '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M7.5 12.2l3 3 6-6.2"/>'],
    'robot'      => ['Asistente',    '<rect x="4" y="7.5" width="16" height="12" rx="3"/><path d="M12 3.5v4M9 16.5h6"/><circle cx="9" cy="12.5" r="1.1"/><circle cx="15" cy="12.5" r="1.1"/>'],
    'grafico'    => ['Reportes',     '<path d="M3 20.5h18"/><path d="M6 20.5v-7M11 20.5V5M16 20.5v-10"/>'],
    'correo'     => ['Correo',       '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6.5 8.5-6.5"/>'],
    'calendario' => ['Calendario',   '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>'],
    'documento'  => ['Documento',    '<path d="M6 2.5h8l4.5 4.5v14.5H6z"/><path d="M13.5 2.5v5h5M9 12.5h6M9 16h6"/>'],
];

const ICONOS_UI = [
    'salir'     => '<path d="M15 4.5h3.5a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H15"/><path d="M10 16.5l-4.5-4.5L10 7.5M5.5 12H16"/>',
    'usuario'   => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c.9-4 3.9-6 7.5-6s6.6 2 7.5 6"/>',
    'usuarios'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.3-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><circle cx="17" cy="9" r="2.5"/><path d="M17.5 14.6c2.2.2 3.7 1.5 4.1 3.9"/>',
    'apps'      => ICONOS_APP['app'][1],
    'escudo'    => ICONOS_APP['escudo'][1],
    'reloj'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
    'lista'     => '<path d="M9 6h11.5M9 12h11.5M9 18h11.5"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
    'buscar'    => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
    'flecha'    => '<path d="M5 12h14M13.5 6.5L19 12l-5.5 5.5"/>',
    'externo'   => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V7.5A1.5 1.5 0 0 1 5.5 6H10"/>',
    'mas'       => '<path d="M12 5v14M5 12h14"/>',
    'llave'     => '<circle cx="7.5" cy="15.5" r="4"/><path d="M10.5 12.5l9-9M16.5 6.5l2.5 2.5M14 9l2 2"/>',
    'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'cerrar'    => '<path d="M6 6l12 12M18 6L6 18"/>',
    'candado'   => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
    'check'     => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    'alerta'    => '<path d="M12 3.5l9.5 16.5h-19z"/><path d="M12 10v4.5M12 17.5h.01"/>',
    'editar'    => '<path d="M4 20h4L19.5 8.5a2.1 2.1 0 0 0-4-4L4 16z"/><path d="M14 6l4 4"/>',
    'inicio'    => '<path d="M3.5 11L12 3.5l8.5 7.5"/><path d="M5.5 9.5v11h13v-11"/>',
];

/**
 * SVG de un ícono (de la interfaz o de app). Nombre desconocido = el genérico.
 * Decorativo por defecto (aria-hidden); si lleva $titulo, se anuncia.
 */
function icono(string $nombre, string $clase = 'ico', ?string $titulo = null): string
{
    $trazo = ICONOS_UI[$nombre] ?? (ICONOS_APP[$nombre][1] ?? ICONOS_APP['app'][1]);
    $aria = $titulo !== null ? ' role="img" aria-label="' . e($titulo) . '"' : ' aria-hidden="true"';
    return '<svg class="' . e($clase) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round"' . $aria . '>' . $trazo . '</svg>';
}

/** Logo "G" de Google a color (botón de ingreso). */
function icono_google(): string
{
    return '<svg class="ico-google" viewBox="0 0 48 48" aria-hidden="true">'
        . '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
        . '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>'
        . '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>'
        . '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
        . '</svg>';
}
