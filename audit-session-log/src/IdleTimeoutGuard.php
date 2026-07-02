<?php

declare(strict_types=1);

namespace Portfolio\AuditSessionLog;

use Closure;

/**
 * Enforces an idle-session timeout. On each request the guard compares the
 * session's last-activity time against the timeout; if exceeded it writes a
 * `session_timeout_logout` audit entry (with user + department, before the
 * session is cleared) and ends the session. Otherwise it refreshes the activity
 * timestamp.
 *
 * Logging the timeout as an audit action makes automatic logouts show up in the
 * same overviews as manual actions — you can see *why* a session ended.
 */
final class IdleTimeoutGuard
{
    public const TIMEOUT_ACTION = 'session_timeout_logout';

    /** @param Closure():int $clock Returns the current unix timestamp. */
    public function __construct(
        private ActionLogger $logger,
        private Closure $clock,
        private int $timeoutSeconds = 1800, // 30 minutes
    ) {
    }

    /**
     * Returns true if the session is still active (and refreshes it), or false
     * if it had expired (in which case it has been audited and cleared).
     */
    public function check(SessionContext $session): bool
    {
        if (!$session->isAuthenticated()) {
            return false;
        }

        $now  = ($this->clock)();
        $last = $session->lastActivity();

        if ($last !== null && ($now - $last) > $this->timeoutSeconds) {
            // Capture identity before clearing, then audit the timeout.
            $this->logger->logSystemEvent(
                (int)$session->userId(),
                $session->departmentId(),
                $session->ipAddress(),
                self::TIMEOUT_ACTION
            );
            $session->clear();
            return false;
        }

        $session->touch($now);
        return true;
    }
}
