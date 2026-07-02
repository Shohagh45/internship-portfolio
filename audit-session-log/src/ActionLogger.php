<?php

declare(strict_types=1);

namespace Portfolio\AuditSessionLog;

use Closure;

/**
 * Writes audit-trail rows for user actions, capturing *which department* the
 * user was acting under at the time — so an audit overview can answer not just
 * "who did what", but "on behalf of which department".
 *
 * The department (participant) is read from the session at the moment of the
 * action, because a user can belong to several departments and switch between
 * them within a session.
 */
final class ActionLogger
{
    /** @param Closure():int $clock Returns the current unix timestamp. */
    public function __construct(
        private AuditLogRepository $repo,
        private Closure $clock,
    ) {
    }

    /**
     * Logs an action performed within the given session context.
     * No-op when there is no authenticated user (nothing to attribute).
     */
    public function log(SessionContext $session, string $action, ?int $clientId = null): void
    {
        if (!$session->isAuthenticated()) {
            return;
        }

        $this->repo->add(
            userId: (int)$session->userId(),
            departmentId: $session->departmentId(),
            ipAddress: $session->ipAddress(),
            action: $action,
            clientId: $clientId,
            timestamp: ($this->clock)(),
        );
    }

    /**
     * Logs a system-generated event (e.g. an automatic timeout logout) for a
     * user/department directly, without needing a live session object.
     */
    public function logSystemEvent(int $userId, ?int $departmentId, string $ipAddress, string $action): void
    {
        $this->repo->add($userId, $departmentId, $ipAddress, $action, null, ($this->clock)());
    }
}
