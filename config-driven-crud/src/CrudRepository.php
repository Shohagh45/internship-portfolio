<?php

declare(strict_types=1);

namespace Portfolio\ConfigDrivenCrud;

/**
 * Storage abstraction the CRUD engine writes through. Implement against PDO,
 * an ORM, etc. Only the columns the engine passes are ever touched.
 */
interface CrudRepository
{
    /**
     * @param string[] $columns
     * @return array<int,array<string,mixed>>
     */
    public function list(string $table, array $columns, string $orderBy): array;

    /** @return array<string,mixed>|null */
    public function find(string $table, string $idField, int|string $id): ?array;

    /** @param array<string,mixed> $data @return int|string new id */
    public function insert(string $table, array $data): int|string;

    /** @param array<string,mixed> $data */
    public function update(string $table, string $idField, int|string $id, array $data): void;

    public function delete(string $table, string $idField, int|string $id): void;
}

/**
 * In-memory repository for the demo/tests. Each "table" is an array of rows
 * keyed by an auto-incrementing id.
 */
final class ArrayCrudRepository implements CrudRepository
{
    /** @var array<string,array<int,array<string,mixed>>> */
    private array $tables = [];
    /** @var array<string,int> */
    private array $sequences = [];

    public function list(string $table, array $columns, string $orderBy): array
    {
        $rows = array_values($this->tables[$table] ?? []);

        // Sort by "<field> ASC|DESC".
        [$field, $dir] = array_pad(explode(' ', trim($orderBy)), 2, 'ASC');
        if ($field !== '') {
            usort($rows, fn($a, $b) => ($a[$field] ?? null) <=> ($b[$field] ?? null));
            if (strtoupper($dir) === 'DESC') {
                $rows = array_reverse($rows);
            }
        }

        // Project only the requested columns.
        return array_map(
            fn($row) => array_intersect_key($row, array_flip($columns)),
            $rows
        );
    }

    public function find(string $table, string $idField, int|string $id): ?array
    {
        return $this->tables[$table][$id] ?? null;
    }

    public function insert(string $table, array $data): int|string
    {
        $id = ($this->sequences[$table] ?? 0) + 1;
        $this->sequences[$table] = $id;
        $this->tables[$table][$id] = array_merge(['id' => $id], $data);
        return $id;
    }

    public function update(string $table, string $idField, int|string $id, array $data): void
    {
        if (isset($this->tables[$table][$id])) {
            $this->tables[$table][$id] = array_merge($this->tables[$table][$id], $data);
        }
    }

    public function delete(string $table, string $idField, int|string $id): void
    {
        unset($this->tables[$table][$id]);
    }
}
