<?php
declare(strict_types=1);

// ============================================================
//  Pantalla de ingreso. Un solo botón: Google Workspace.
//  Si ya hay sesión, sigue de largo (a lo pendiente o al panel).
// ============================================================

if (usuario_actual() !== null) {
    redirigir(destino_post_login());
}

$motivos = [
    'dominio'       => 'Esa cuenta no es de Intellihelp. Entrá con tu cuenta @' . cfg('google.allowed_domain', '') . '.',
    'no_registrado' => 'Tu cuenta todavía no está dada de alta en ITHub. Pedíselo a un administrador.',
    'inactivo'      => 'Tu usuario de ITHub está desactivado. Si es un error, avisale a un administrador.',
    'google'        => 'Google no confirmó el ingreso. Probá de nuevo.',
    'vencido'       => 'El ingreso tardó demasiado. Probá de nuevo.',
];
$error = $motivos[$_GET['error'] ?? ''] ?? null;

vista('login', [
    'layout'    => 'auth',
    'titulo'    => 'Ingresar',
    'error'     => $error,
    'salio'     => isset($_GET['salio']),
    'pendiente' => !empty($_SESSION['oidc_pendiente']),
    'app_pendiente' => $_SESSION['oidc_pendiente_app'] ?? null,
    'habilitado' => google_habilitado(),
]);

