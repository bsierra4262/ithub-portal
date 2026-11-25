<?php
declare(strict_types=1);

// ============================================================
//  Acceso a la base.
//
//  Una sola conexión PDO por pedido (db()), con errores como excepciones y
//  resultados como arrays asociativos. Las funciones cortas q / q1 / qv / qx
//  cubren todo lo que hace la aplicación; siempre con parámetros, nunca
//  concatenando valores en el SQL.
//
//  Motores: MariaDB/MySQL en producción y SQLite para desarrollo local y
//  tests. El SQL de la aplicación se escribe en el subconjunto que entienden
//  los dos (sin ON DUPLICATE KEY, sin NOW(): las fechas las arma ahora()).
// ============================================================

/** Conexión de la aplicación. La arma con $CONFIG['db'] la primera vez. */
function db(): PDO
{
    static $pdo = null;
    if (isset($GLOBALS['__pdo_test']) && $GLOBALS['__pdo_test'] instanceof PDO) {
        return $GLOBALS['__pdo_test'];
    }
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = $GLOBALS['CONFIG']['db'] ?? [];
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['path'], null, null, $opciones);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $c['host'] ?? '127.0.0.1', $c['name'] ?? '', $c['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', $opciones);
    // La base trabaja en la misma zona que PHP: las fechas se guardan como
    // las arma ahora() y se comparan como texto.
    return $pdo;
}

/** Usa otra conexión (tests). null vuelve a la normal. */
function db_usar(?PDO $pdo): void
{
    $GLOBALS['__pdo_test'] = $pdo;
}

/** Ejecuta y devuelve todas las filas. */
function q(string $sql, array $p = []): array
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

/** Primera fila o null. */
function q1(string $sql, array $p = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($p);
    $f = $st->fetch();
    return $f === false ? null : $f;
}

/** Primer valor de la primera fila, o null. */
function qv(string $sql, array $p = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($p);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

/** Ejecuta un INSERT/UPDATE/DELETE y devuelve las filas afectadas. */
function qx(string $sql, array $p = []): int
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->rowCount();
}

/** Id del último INSERT. */
function qid(): int
{
    return (int)db()->lastInsertId();
}

/**
 * Corre $fn dentro de una transacción: si tira una excepción, se deshace
 * todo y la excepción sigue su camino.
 */
function tx(callable $fn): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $r = $fn();
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Traduce app/db/schema.sql al dialecto de SQLite.
 *
 * El esquema ya está escrito para que esto sea poco: sólo cambia la clave
 * autoincremental y se va la cola `ENGINE=... ;` de cada tabla.
 */
function sql_a_sqlite(string $sql): string
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\)\s*ENGINE=[^;]*;/i', ');', $sql);
    return $sql;
}

/** Ejecuta un archivo SQL sentencia por sentencia (separadas por ';' al final de línea). */
function db_ejecutar_sql(string $sql): void
{
    if ((db()->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite') {
        $sql = sql_a_sqlite($sql);
    } else {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    }
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $sentencia) {
        if (trim($sentencia) !== '') {
            db()->exec($sentencia);
        }
    }
}
