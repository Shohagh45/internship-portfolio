<?php

declare(strict_types=1);

namespace Portfolio\AuditSessionLog;

/**
 * Thin wrapper over the session data the audit + timeout logic needs. Backed by
 * a plain array here so it is testable; in the app it would read/write $_SESSION
 * (user_id, pk_participant = department, last_activity, remote IP).
 */
final class SessionContext
{
    /** @param array<string,mixed> $data */
    public function __construct(private array $data = [])
    {
    }

    public function isAuthenticated(): bool
    {
        return !empty($this->data['user_id']);
    }

    public function userId(): ?int
    {
        return isset($this->data['user_id']) ? (int)$this->data['user_id'] : null;
    }

    /** Department / participant id the user is currently acting under. */
    public function departmentId(): ?int
    {
        return isset($this->data['department_id']) ? (int)$this->data['department_id'] : null;
    }

    public function ipAddress(): string
    {
        return (string)($this->data['ip_address'] ?? '0.0.0.0');
    }

    public function lastActivity(): ?int
    {
        return isset($this->data['last_activity']) ? (int)$this->data['last_activity'] : null;
    }

    public function touch(int $timestamp): void
    {
        $this->data['last_activity'] = $timestamp;
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
