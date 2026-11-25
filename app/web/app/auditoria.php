<?php
declare(strict_types=1);

// ============================================================
//  Auditoría: quién hizo qué, sobre quién o qué, cuándo y desde dónde.
//
//  Se anota con auditar(). Nunca corta el pedido: si la escritura falla
//  (base caída a mitad de camino), se deja en el log de PHP y se sigue.
//  No se guardan secretos en `detalle`: ni códigos, ni tokens, ni el
//  secreto de una app.
// ============================================================

/** Acción → texto para la pantalla de auditoría. */
const ACCIONES_AUDITORIA = [
    'login'              => 'Ingresó a ITHub',
    'login_rechazado'    => 'Ingreso rechazado',
    'salida'             => 'Cerró sesión',
    'sesion_cerrada'     => 'Cerró una sesión',
    'sesiones_cerradas'  => 'Cerró todas las sesiones',
    'app_ingreso'        => 'Entró a una aplicación',
    'app_denegada'       => 'Acceso denegado a una aplicación',
    'usuario_creado'     => 'Creó un usuario',
    'usuario_editado'    => 'Editó un usuario',
    'permiso_otorgado'   => 'Otorgó acceso',
    'permiso_cambiado'   => 'Cambió un rol',
    'permiso_quitado'    => 'Quitó acceso',
    'app_creada'         => 'Creó una aplicación',
    'app_editada'        => 'Editó una aplicación',
    'secreto_generado'   => 'Generó el secreto de una aplicación',
];

/**
 * Anota un evento.
 *
 * @param int|null    $usuarioId     quién lo hizo (null = anónimo o sistema)
 * @param string      $accion        clave de ACCIONES_AUDITORIA
 * @param int|null    $aplicacionId  app involucrada, si hay
 * @param string|null $objetivo      sobre quién o qué (email, clave de app)
 * @param array       $detalle       datos extra (sin secretos)
 */
function auditar(?int $usuarioId, string $accion, ?int $aplicacionId = null, ?string $objetivo = null, array $detalle = []): void
{
    try {
        qx('INSERT INTO auditoria (usuario_id, accion, aplicacion_id, objetivo, detalle, ip, creado_en)
            VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $usuarioId,
            $accion,
            $aplicacionId,
            $objetivo !== null ? mb_substr($objetivo, 0, 190) : null,
            $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            PHP_SAPI === 'cli' ? null : ip_cliente(),
            ahora(),
        ]);
    } catch (Throwable $e) {
        error_log('[ithub] auditoría no registrada (' . $accion . '): ' . $e->getMessage());
    }
}
