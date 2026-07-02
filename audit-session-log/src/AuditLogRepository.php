<?php

declare(strict_types=1);

namespace Portfolio\AuditSessionLog;

/**
 * Persists audit rows and reads them back for the overview screens. Implement
 * against your database (the original inserts into an action-log table and, for
 * overviews, LEFT JOINs the departments table to show the department name).
 */
interface AuditLogRepository
{
    public function add(
        int $userId,
        ?int $departmentId,
        string $ipAddress,
        string $action,
        ?int $clientId,
        int $timestamp
    ): void;

    /**
     * Most recent rows, newest first, each enriched with the department name.
     * @return array<int,array<string,mixed>>
     */
    public function recent(int $limit): array;
}

/**
 * In-memory implementation for the demo/tests. Constructed with a departments
 * map so `recent()` can resolve the department name (mirroring the LEFT JOIN in
 * the real overview query).
 */
final class ArrayAuditLogRepository implements AuditLogRepository
{
    /** @var array<int,array<string,mixed>> */
    private array $rows = [];

    /** @param array<int,string> $departments  id => name */
    public function __construct(private array $departments = [])
    {
    }

    public function add(int $userId, ?int $departmentId, string $ipAddress, string $action, ?int $clientId, int $timestamp): void
    {
        $this->rows[] = [
            'user_id'       => $userId,
            'department_id' => $departmentId,
            'ip_address'    => $ipAddress,
            'action'        => $action,
            'client_id'     => $clientId,
            'timestamp'     => $timestamp,
        ];
    }

    public function recent(int $limit): array
    {
        $rows = array_reverse($this->rows); // newest first
        $rows = array_slice($rows, 0, $limit);

        return array_map(function (array $row) {
            // Department may be null for rows written before the feature shipped.
            $row['department'] = $row['department_id'] !== null
                ? ($this->departments[$row['department_id']] ?? null)
                : null;
            return $row;
        }, $rows);
    }
}
