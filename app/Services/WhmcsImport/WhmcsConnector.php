<?php

namespace App\Services\WhmcsImport;

use PDO;
use RuntimeException;

/**
 * A strictly read-only connection to a WHMCS database.
 *
 * The importer never writes to the source: this class exposes only SELECT,
 * SHOW and information-schema reads, uses prepared statements for every
 * value, and quotes identifiers explicitly (user input is never spliced
 * into SQL). As a second line of defence the session is switched to
 * read-only where the server allows it, and any failure to do so is ignored
 * rather than made a hard requirement.
 */
class WhmcsConnector
{
    protected PDO $pdo;

    public function __construct(array $config)
    {
        $host = $config['host'] ?? '';
        $port = (int) ($config['port'] ?? 3306);
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = (string) ($config['password'] ?? '');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (\PDOException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        // Best effort: a read-only session guards against accidental writes
        // even if a future caller adds a stray query.
        try {
            $this->pdo->exec('SET SESSION TRANSACTION READ ONLY');
        } catch (\Throwable) {
            // Not fatal — the connector's API still only issues reads.
        }
    }

    /** Whether the credentials work and the server answers a trivial query. */
    public function test(): bool
    {
        $this->pdo->query('SELECT 1');

        return true;
    }

    /**
     * The names of the tables in the database.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return $this->pdo
            ->query('SHOW TABLES')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Column names (plus type) of a table, in physical order.
     *
     * @return list<array{name: string, type: string}>
     */
    public function columns(string $table): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION'
        );
        $stmt->execute([$table]);

        return array_map(
            fn (array $row) => ['name' => $row['COLUMN_NAME'], 'type' => $row['COLUMN_TYPE']],
            $stmt->fetchAll()
        );
    }

    /**
     * A limited number of rows from a table, exactly as WHMCS stores them.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $table, int $limit): array
    {
        $limit = max(1, min($limit, 1000));

        $stmt = $this->pdo->prepare(sprintf('SELECT * FROM %s LIMIT ?', $this->quoteIdentifier($table)));
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function count(string $table): int
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT COUNT(*) FROM %s', $this->quoteIdentifier($table)));
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Iterate every row of a table in bounded chunks, ordered by the primary
     * key when one is found (WHMCS tables carry an `id`). Returns rows seen.
     */
    public function each(string $table, int $size, callable $callback): int
    {
        return $this->iterate($table, $size, $callback);
    }

    /**
     * Like each(), but merges each row's client custom-field values under
     * `custom:{fieldname}` keys before handing it over.
     *
     * @param  list<array{id: int, name: string}>  $customFields
     */
    public function eachEnriched(string $table, string $prefix, array $customFields, int $size, callable $callback): int
    {
        return $this->iterate($table, $size, $callback, $prefix, $customFields);
    }

    /**
     * @param  list<array{id: int, name: string}>  $customFields
     */
    protected function iterate(string $table, int $size, callable $callback, ?string $prefix = null, ?array $customFields = null): int
    {
        $size = max(1, min($size, 1000));
        $order = $this->orderColumn($table);
        $offset = 0;
        $seen = 0;

        do {
            $sql = sprintf('SELECT * FROM %s', $this->quoteIdentifier($table));
            if ($order !== null) {
                $sql .= sprintf(' ORDER BY %s', $this->quoteIdentifier($order));
            }
            $sql .= ' LIMIT ? OFFSET ?';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(1, $size, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll();

            if ($prefix !== null && $customFields !== null && $customFields !== []) {
                $this->enrichRows($rows, $prefix, $customFields);
            }

            foreach ($rows as $row) {
                $callback($row);
                $seen++;
            }

            $offset += $size;
        } while (count($rows) === $size);

        return $seen;
    }

    /**
     * Merge client custom-field values into already-fetched rows, keyed as
     * `custom:{fieldname}` so they cannot collide with a real table column.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{id: int, name: string}>  $customFields
     */
    public function enrichRows(array &$rows, string $prefix, array $customFields): void
    {
        if ($rows === [] || $customFields === []) {
            return;
        }

        $ids = array_values(array_filter(array_map(fn (array $row) => (int) ($row['id'] ?? 0), $rows)));
        $values = $this->customFieldValues($prefix, $ids);

        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            foreach ($customFields as $field) {
                $row['custom:'.$field['name']] = $values[$id][$field['id']] ?? null;
            }
        }
        unset($row);
    }

    /**
     * The client custom-field definitions (WHMCS "custom fields" live in their
     * own table, not in tblclients).
     *
     * @return list<array{id: int, name: string}>
     */
    public function clientCustomFields(string $prefix): array
    {
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT id, fieldname FROM %s WHERE type = ? AND relid = 0 ORDER BY id',
            $this->quoteIdentifier($prefix.'customfields')
        ));
        $stmt->execute(['client']);

        return array_map(
            fn (array $row) => ['id' => (int) $row['id'], 'name' => $row['fieldname']],
            $stmt->fetchAll()
        );
    }

    /**
     * Custom-field values for a set of client ids.
     *
     * @param  list<int>  $ids
     * @return array<int, array<int, string>>
     */
    public function customFieldValues(string $prefix, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $table = $this->quoteIdentifier($prefix.'customfieldsvalues');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(sprintf(
            'SELECT relid, fieldid, value FROM %s WHERE relid IN (%s)',
            $table,
            $placeholders
        ));
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['relid']][(int) $row['fieldid']] = $row['value'];
        }

        return $result;
    }

    /** The column to order by when paging, `id` when present, else null. */
    protected function orderColumn(string $table): ?string
    {
        foreach ($this->columns($table) as $column) {
            if (strtolower($column['name']) === 'id') {
                return $column['name'];
            }
        }

        return null;
    }

    /**
     * Quote an identifier (table or column name) with backticks, refusing
     * anything that is not a plain identifier. Never splice user input into
     * SQL unquoted.
     */
    public function quoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        $identifier = str_replace('`', '', $identifier);

        if ($identifier === '' || ! preg_match('/^[A-Za-z0-9_$]+$/', $identifier)) {
            throw new RuntimeException('Invalid SQL identifier: '.$identifier);
        }

        return '`'.$identifier.'`';
    }

    /** The full, quoted table name including the configured prefix. */
    public function table(string $prefix, string $table): string
    {
        return $this->quoteIdentifier($prefix.$table);
    }
}
