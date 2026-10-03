<?php
defined('ROOT') || exit;

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['config']['db'];
        $dsn = 'mysql:host=' . $c['host'] . (!empty($c['port']) ? ';port=' . $c['port'] : '') . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Emulated prepares = one round-trip per query (faster on shared hosts)
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
    }
    return $pdo;
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

function db_tx(callable $fn)
{
    $pdo = db();
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
