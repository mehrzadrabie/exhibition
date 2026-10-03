<?php
defined('ROOT') || exit;

class DbBusyException extends RuntimeException
{
}

/**
 * Lazily opened connection. Shared hosts cap concurrent connections per user
 * (often 15–30), so connecting retries briefly before giving up, and code that
 * waits on external services calls db_close() first to free the slot.
 */
function db()
{
    if (isset($GLOBALS['__pdo'])) return $GLOBALS['__pdo'];
    $c = $GLOBALS['config']['db'];
    $dsn = 'mysql:host=' . $c['host'] . (!empty($c['port']) ? ';port=' . $c['port'] : '') . ';dbname=' . $c['name'] . ';charset=utf8mb4';
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Emulated prepares = one round-trip per query (faster on shared hosts)
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::ATTR_TIMEOUT => 5,
    ];
    $waits = [0, 120000, 250000, 500000, 900000]; // ~1.8s total
    foreach ($waits as $i => $us) {
        if ($us) usleep($us + random_int(0, 80000));
        try {
            $GLOBALS['__pdo'] = new PDO($dsn, $c['user'], $c['pass'], $opts);
            return $GLOBALS['__pdo'];
        } catch (PDOException $e) {
            // 1040 too many connections, 1203/1226 per-user limits, 2002 socket busy
            $busy = preg_match('/\b(1040|1203|1226|2002|2006)\b/', $e->getMessage());
            if (!$busy) throw $e;
            if ($i === count($waits) - 1) throw new DbBusyException('Database busy: ' . $e->getMessage());
        }
    }
    throw new DbBusyException('Database busy');
}

/** Release the connection (before slow external HTTP calls). db() reconnects on demand. */
function db_close()
{
    $GLOBALS['__pdo'] = null;
}

function db_query($sql, array $params = [])
{
    if (!$params) return db()->query($sql);
    $st = db()->prepare($sql);
    foreach (array_values($params) as $i => $v) {
        $st->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : ($v === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
    }
    $st->execute();
    return $st;
}

function db_row($sql, array $params = [])
{
    $r = db_query($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function db_all($sql, array $params = [])
{
    return db_query($sql, $params)->fetchAll();
}

function db_val($sql, array $params = [])
{
    $v = db_query($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function db_col($sql, array $params = [])
{
    return db_query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

/** @return int affected rows */
function db_exec($sql, array $params = [])
{
    return db_query($sql, $params)->rowCount();
}

function db_insert($table, array $data)
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
    db_query($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function db_update($table, array $data, $where, array $whereParams = [])
{
    $set = [];
    foreach (array_keys($data) as $c) $set[] = $c . ' = ?';
    return db_exec('UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE ' . $where, array_merge(array_values($data), $whereParams));
}

/** "?,?,?" for an IN() list of $n items */
function db_in($n)
{
    return rtrim(str_repeat('?,', max(1, $n)), ',');
}

/**
 * Run $fn in a transaction. Deadlocks / lock wait timeouts (normal under heavy
 * concurrent booking) are retried automatically; the closure must only touch the DB.
 */
function db_tx(callable $fn)
{
    $pdo = db();
    for ($attempt = 1; ; $attempt++) {
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $retry = $e instanceof PDOException && preg_match('/\b(1213|1205)\b/', $e->getMessage()) && $attempt < 4;
            if (!$retry) throw $e;
            usleep(random_int(20000, 60000) * $attempt);
        }
    }
}
