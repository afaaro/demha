<?php

namespace System\Library;

use BadMethodCallException;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    private PDO $pdo;
    private string $prefix = '';

    public function __construct(array $config = [])
    {
        $host = $config['hostname'] ?? $config['host'] ?? 'localhost';
        $port = (int) ($config['port'] ?? 3306);
        $name = $config['database'] ?? $config['name'] ?? '';
        $user = $config['username'] ?? $config['user'] ?? '';
        $pass = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $this->prefix = (string) ($config['prefix'] ?? '');

        if ($name === '' || $user === '') {
            throw new RuntimeException('Database name and username are required.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function query(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): object
    {
        $sql = $this->replacePrefix($sql);
        $result = (object) [
            'sql'       => $sql,
            'row'       => [],
            'rows'      => [],
            'num_rows'  => 0,
            'affected'  => 0,
            'value'     => null,
            'pairs'     => [],
        ];

        $stmt = $this->pdo->prepare($sql);

        $hasNamed = count(array_filter(array_keys($params), 'is_string')) > 0;
        $stmt->execute($hasNamed ? $params : array_values($params));

        if ($stmt->columnCount() > 0) {
            $rows = $stmt->fetchAll($fetchMode);
            $result->rows = $rows;
            $result->row = $rows[0] ?? [];
            $result->num_rows = count($rows);

            if ($result->num_rows > 0) {
                $first = $result->row;
                $result->value = reset($first);

                if (count($first) === 2) {
                    $keys = array_keys($first);
                    $result->pairs = array_column($rows, $keys[1], $keys[0]);
                }
            }
        } else {
            $result->affected = $stmt->rowCount();
        }

        return $result;
    }

    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($this->replacePrefix($sql));
    }

    public function findOne(string $table, mixed $value = null, string $column = 'id'): array
    {
        $table = $this->table($table);
        $column = $this->sanitizeIdentifier($column);

        if ($value === null) {
            return $this->query("SELECT * FROM {$table} WHERE `{$column}` IS NULL LIMIT 1")->row;
        }

        return $this->query(
            "SELECT * FROM {$table} WHERE `{$column}` = ? LIMIT 1",
            [$value]
        )->row;
    }

    public function find(string $table, array $where = [], string $order = ''): array
    {
        $table = $this->table($table);
        [$sql, $bind] = $this->buildWhereClause($table, $where);

        if ($order !== '') {
            $sql .= ' ORDER BY ' . $this->sanitizeOrderBy($order);
        }

        return $this->query($sql, $bind)->rows;
    }

    public function first(string $table, array $where = [], string $order = ''): array
    {
        $table = $this->table($table);
        [$sql, $bind] = $this->buildWhereClause($table, $where);

        if ($order !== '') {
            $sql .= ' ORDER BY ' . $this->sanitizeOrderBy($order);
        }

        return $this->query($sql . ' LIMIT 1', $bind)->row;
    }

    public function count(string $table, array $where = []): int
    {
        $table = $this->table($table);
        [$sql, $bind] = $this->buildWhereClause($table, $where, 'COUNT(*)');
        return (int) $this->query($sql, $bind)->value;
    }

    public function exists(string $table, array $where): bool
    {
        return $this->count($table, $where) > 0;
    }

    /**
     * @return int Last insert ID
     */
    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Insert data cannot be empty.');
        }

        $table = $this->table($table);
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = 'INSERT INTO ' . $table . ' ('
            . implode(', ', array_map(fn($col) => '`' . $this->sanitizeIdentifier((string) $col) . '`', $columns))
            . ') VALUES (' . implode(', ', $placeholders) . ')';

        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): bool
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Update data cannot be empty.');
        }
        if (empty($where)) {
            throw new InvalidArgumentException('Update WHERE condition cannot be empty.');
        }

        $table = $this->table($table);
        $set = [];
        $values = [];

        foreach ($data as $col => $val) {
            $set[] = '`' . $this->sanitizeIdentifier((string) $col) . '` = ?';
            $values[] = $val;
        }

        $whereClause = [];
        foreach ($where as $col => $val) {
            $whereClause[] = '`' . $this->sanitizeIdentifier((string) $col) . '` = ?';
            $values[] = $val;
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $whereClause);
        return $this->query($sql, $values)->affected > 0;
    }

    public function delete(string $table, array $where): bool
    {
        if (empty($where)) {
            throw new InvalidArgumentException('Delete WHERE condition cannot be empty.');
        }

        $table = $this->table($table);
        $whereClause = [];
        $values = [];

        foreach ($where as $col => $val) {
            $whereClause[] = '`' . $this->sanitizeIdentifier((string) $col) . '` = ?';
            $values[] = $val;
        }

        $sql = 'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $whereClause);
        return $this->query($sql, $values)->affected > 0;
    }

    /**
     * Insert or update on duplicate key.
     * @return int Last insert ID
     */
    public function upsert(string $table, array $data, array $uniqueColumns): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Data cannot be empty.');
        }

        $table = $this->table($table);
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = 'INSERT INTO ' . $table . ' (' .
            implode(', ', array_map(fn($col) => '`' . $this->sanitizeIdentifier((string) $col) . '`', $columns)) .
            ') VALUES (' . implode(', ', $placeholders) . ')';

        $updates = [];
        foreach ($data as $col => $val) {
            if (!in_array($col, $uniqueColumns, true)) {
                $updates[] = '`' . $this->sanitizeIdentifier((string) $col) . '` = VALUES(`' . $this->sanitizeIdentifier((string) $col) . '`)';
            }
        }

        if (!empty($updates)) {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        }

        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    // $this->db->paginate($where, $params, $page, $perPage, 'name ASC')
    public function paginate(string $table, array $where, array $params, int $page = 1, int $perPage = 20, string $order = ''): array
    {
        if ($page < 1) { $page = 1; }
        if ($perPage < 1) { $perPage = 20; }

        $offset = ($page - 1) * $perPage;
        $whereClause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $orderClause = $order !== '' ? ' ORDER BY ' . $this->sanitizeOrderBy($order) : '';

        // Count total rows
        $countSql = "SELECT COUNT(*) FROM {$table}{$whereClause}";
        $totalRows = (int) $this->query($countSql, $params)->value;

        // Fetch paginated rows
        $sql = "SELECT * FROM {$table}{$whereClause}{$orderClause} LIMIT ? OFFSET ?";
        $rows = $this->query($sql, array_merge($params, [$perPage, $offset]))->rows;

        return [
            'data' => $rows,
            'total' => $totalRows,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($totalRows / max($perPage, 1)),
        ];
    }

    public function insertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function escape(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $quoted = $this->pdo->quote($value);
        return $quoted !== false ? substr($quoted, 1, -1) : addslashes($value);
    }

    public function beginTransaction(): bool  { return $this->pdo->beginTransaction(); }
    public function commit(): bool            { return $this->pdo->commit(); }
    public function rollBack(): bool          { return $this->pdo->rollBack(); }
    public function inTransaction(): bool     { return $this->pdo->inTransaction(); }
    public function pdo(): PDO                { return $this->pdo; }

    public function table(string $table): string
    {
        return str_starts_with($table, '#__') ? $table : '#__' . $table;
    }

    private function replacePrefix(string $sql): string
    {
        return str_replace('#__', $this->prefix, $sql);
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    private function sanitizeIdentifier(string $identifier): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '', str_replace('`', '', $identifier));
    }

    private function sanitizeOrderBy(string $order): string
    {
        $parts = explode(',', $order);
        $safe = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (!preg_match('/^`?([A-Za-z0-9_]+)`?(\s+(ASC|DESC))?$/i', $part, $matches)) {
                throw new InvalidArgumentException('Invalid ORDER BY value: ' . $part);
            }
            $col = $this->sanitizeIdentifier($matches[1]);
            $dir = isset($matches[3]) ? ' ' . strtoupper($matches[3]) : '';
            $safe[] = "`{$col}`{$dir}";
        }
        return implode(', ', $safe);
    }

    private function buildWhereClause(string $table, array $where, string $select = '*'): array
    {
        $sql = "SELECT {$select} FROM {$table}";
        $bind = [];
        if (empty($where)) return [$sql, $bind];

        $clauses = [];
        foreach ($where as $col => $val) {
            $col = $this->sanitizeIdentifier((string) $col);
            if ($val === null) {
                $clauses[] = "`{$col}` IS NULL";
            } else {
                $clauses[] = "`{$col}` = ?";
                $bind[] = $val;
            }
        }
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
        return [$sql, $bind];
    }

    public function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function quote(string $value): string
    {
        return $this->pdo->quote($value);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (method_exists($this->pdo, $name)) {
            return $this->pdo->$name(...$arguments);
        }
        throw new BadMethodCallException("Call to undefined method Database::{$name}()");
    }
}