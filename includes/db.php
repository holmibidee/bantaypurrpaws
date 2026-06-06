<?php
// ============================================================
//  BantayPurrPaws — MySQL (XAMPP) database layer
//
//  Copy .env.example → .env and set DB_* credentials.
//  Import sql/schema.sql in phpMyAdmin or: mysql < sql/schema.sql
// ============================================================

require_once __DIR__ . '/env.php';

load_env_file(dirname(__DIR__) . '/.env');

$_dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$_dbPort = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$_dbName = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$_dbUser = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$_dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

if ($_dbName === '' || $_dbUser === '') {
    error_log('BantayPurrPaws: MySQL credentials missing from environment.');
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Server configuration error. Please contact the administrator.']));
    }
}

/** @var PDO|null */
$GLOBALS['_bantay_pdo'] = null;

const DB_ALLOWED_TABLES = [
    'users',
    'rescue_reports',
    'report_logs',
    'pets',
    'pet_images',
    'adoption_applications',
    'notifications',
    'otp_tokens',
    'staff_invites',
];

function getDB(): PDO
{
    if ($GLOBALS['_bantay_pdo'] instanceof PDO) {
        return $GLOBALS['_bantay_pdo'];
    }

    $host = 'sql103.infinityfree.com';
    $port = '3306';
    $name = 'if0_42111065_bantaypurrpaws';
    $user = 'if0_42111065';
    $pass = 'wottaberu1113';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('BantayPurrPaws MySQL connection failed: ' . $e->getMessage());
        throw new RuntimeException('Database connection failed.', 0, $e);
    }

    $GLOBALS['_bantay_pdo'] = $pdo;
    return $pdo;
}

function db_assert_table(string $table): string
{
    if (!in_array($table, DB_ALLOWED_TABLES, true)) {
        throw new InvalidArgumentException('Invalid table: ' . $table);
    }
    return '`' . $table . '`';
}

function db_cast_filter_value(mixed $value): mixed
{
    if ($value === 'true') {
        return 1;
    }
    if ($value === 'false') {
        return 0;
    }
    if (is_numeric($value) && !str_contains((string) $value, '.')) {
        return (int) $value;
    }
    return $value;
}

function db_cast_write_value(mixed $value): mixed
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    return $value;
}

/**
 * Parse PostgREST-style filter strings used across the app.
 *
 * @return array{select: string, order: string, limit: ?int, where: string, params: array}
 */
function db_parse_filters(string $filters): array
{
    $select     = '*';
    $order      = '';
    $limit      = null;
    $conditions = [];
    $params     = [];

    if ($filters === '') {
        return [
            'select' => $select,
            'order'  => $order,
            'limit'  => $limit,
            'where'  => '',
            'params' => $params,
        ];
    }

    $parts = explode('&', $filters);

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (str_starts_with($part, 'select=')) {
            $cols = substr($part, 7);
            $select = $cols === '*' ? '*' : implode(', ', array_map(
                static fn ($c) => '`' . preg_replace('/[^a-zA-Z0-9_]/', '', trim($c)) . '`',
                explode(',', $cols)
            ));
            continue;
        }
        if (str_starts_with($part, 'order=')) {
            $orderParts = [];
            foreach (explode(',', substr($part, 6)) as $segment) {
                if (!preg_match('/^([a-zA-Z0-9_]+)\.(asc|desc)$/i', trim($segment), $m)) {
                    continue;
                }
                $orderParts[] = '`' . $m[1] . '` ' . strtoupper($m[2]);
            }
            $order = implode(', ', $orderParts);
            continue;
        }
        if (str_starts_with($part, 'limit=')) {
            $limit = max(1, (int) substr($part, 6));
            continue;
        }

        if (!preg_match('/^([a-zA-Z0-9_]+)=(eq|neq|gt|gte|lt|lte|in)\.(.+)$/s', $part, $m)) {
            continue;
        }

        $column   = $m[1];
        $operator = $m[2];
        $rawValue = urldecode($m[3]);

        if ($operator === 'in') {
            if (!preg_match('/^\((.*)\)$/', $rawValue, $inMatch)) {
                continue;
            }
            $values = array_filter(array_map('trim', explode(',', $inMatch[1])), static fn ($v) => $v !== '');
            if ($values === []) {
                $conditions[] = '1=0';
                continue;
            }
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $conditions[] = "`{$column}` IN ({$placeholders})";
            foreach ($values as $v) {
                $params[] = db_cast_filter_value($v);
            }
            continue;
        }

        $value = db_cast_filter_value($rawValue);

        $sqlOp = match ($operator) {
            'eq'  => '=',
            'neq' => '<>',
            'gt'  => '>',
            'gte' => '>=',
            'lt'  => '<',
            'lte' => '<=',
            default => '=',
        };

        $conditions[] = "`{$column}` {$sqlOp} ?";
        $params[]     = $value;
    }

    return [
        'select' => $select,
        'order'  => $order,
        'limit'  => $limit,
        'where'  => $conditions === [] ? '' : implode(' AND ', $conditions),
        'params' => $params,
    ];
}

function db_select(string $table, string $filters = '', bool $single = false): mixed
{
    try {
        $tableSql = db_assert_table($table);
        $parsed   = db_parse_filters($filters);

        $sql = 'SELECT ' . $parsed['select'] . ' FROM ' . $tableSql;
        if ($parsed['where'] !== '') {
            $sql .= ' WHERE ' . $parsed['where'];
        }
        if ($parsed['order'] !== '') {
            $sql .= ' ORDER BY ' . $parsed['order'];
        }
        if ($parsed['limit'] !== null) {
            $sql .= ' LIMIT ' . (int) $parsed['limit'];
        }

        $stmt = getDB()->prepare($sql);
        $stmt->execute($parsed['params']);
        $rows = $stmt->fetchAll();

        return $single ? ($rows[0] ?? null) : $rows;
    } catch (Throwable $e) {
        error_log('db_select(' . $table . '): ' . $e->getMessage());
        return $single ? null : [];
    }
}

function db_select_cols(string $table, string $cols, string $filters = '', bool $single = false): mixed
{
    $colFilters = 'select=' . $cols . ($filters !== '' ? '&' . $filters : '');
    return db_select($table, $colFilters, $single);
}

function db_count(string $table, string $filters = ''): int
{
    try {
        $tableSql = db_assert_table($table);
        $parsed   = db_parse_filters($filters);

        $sql = 'SELECT COUNT(*) FROM ' . $tableSql;
        if ($parsed['where'] !== '') {
            $sql .= ' WHERE ' . $parsed['where'];
        }

        $stmt = getDB()->prepare($sql);
        $stmt->execute($parsed['params']);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('db_count(' . $table . '): ' . $e->getMessage());
        return 0;
    }
}

function db_insert(string $table, array $data, bool $useService = true): ?array
{
    unset($useService);

    try {
        $tableSql = db_assert_table($table);
        if ($data === []) {
            return null;
        }

        $columns      = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $colList      = implode(',', array_map(static fn ($c) => '`' . $c . '`', $columns));

        $pdo  = getDB();
        $sql  = "INSERT INTO {$tableSql} ({$colList}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_map('db_cast_write_value', array_values($data)));

        $id = (int) $pdo->lastInsertId();
        if ($id > 0) {
            $row = db_select($table, 'id=eq.' . $id, true);
            if (is_array($row) && $row !== []) {
                return $row;
            }
            $data['id'] = $id;
            return $data;
        }

        return $data;
    } catch (Throwable $e) {
        error_log('db_insert(' . $table . '): ' . $e->getMessage());
        return null;
    }
}

function db_update(string $table, array $data, string $filters): bool
{
    try {
        if ($data === []) {
            return false;
        }

        $tableSql = db_assert_table($table);
        $parsed   = db_parse_filters($filters);

        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]   = '`' . $col . '` = ?';
            $params[] = db_cast_write_value($val);
        }

        $sql = 'UPDATE ' . $tableSql . ' SET ' . implode(', ', $sets);
        if ($parsed['where'] !== '') {
            $sql .= ' WHERE ' . $parsed['where'];
            $params = array_merge($params, $parsed['params']);
        }

        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (Throwable $e) {
        error_log('db_update(' . $table . '): ' . $e->getMessage());
        return false;
    }
}

function db_delete(string $table, string $filters): bool
{
    try {
        $tableSql = db_assert_table($table);
        $parsed   = db_parse_filters($filters);

        $sql = 'DELETE FROM ' . $tableSql;
        if ($parsed['where'] !== '') {
            $sql .= ' WHERE ' . $parsed['where'];
        }

        $stmt = getDB()->prepare($sql);
        $stmt->execute($parsed['params']);
        return true;
    } catch (Throwable $e) {
        error_log('db_delete(' . $table . '): ' . $e->getMessage());
        return false;
    }
}

/**
 * Atomically approve an adoption application (PDO transaction).
 */
function approveAdoption(int $applicationId, int $petId): void
{
    $pdo = getDB();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE adoption_applications
            SET status = 'approved', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$applicationId]);

        $stmt = $pdo->prepare("
            UPDATE pets
            SET status = 'adopted', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$petId]);

        $stmt = $pdo->prepare("
            UPDATE adoption_applications
            SET status = 'rejected', updated_at = NOW()
            WHERE pet_id = ?
              AND id <> ?
              AND status = 'pending'
        ");
        $stmt->execute([$petId, $applicationId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function generateReportCode(): string
{
    return 'BPP-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
}

function sanitize(?string $value): string
{
    return htmlspecialchars(strip_tags(trim((string) ($value ?? ''))), ENT_QUOTES, 'UTF-8');
}
