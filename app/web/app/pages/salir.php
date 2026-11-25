<?php
declare(strict_types=1);

// ============================================================
//  Cerrar sesión: cierra la sesión única (y con ella, en todas las apps a
//  las que se entró) y olvida la cookie. Sólo por POST con CSRF: un enlace
//  o una imagen de otro sitio no puede sacar a nadie.
// ============================================================

requerir_post();
cerrar_sesion($yo['sid'], 'salida', $yo['id']);
auditar($yo['id'], 'salida', null, $yo['email']);
salir_local();
redirigir(url('login', ['salio' => 1]));
