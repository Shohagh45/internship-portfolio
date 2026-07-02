<?php

declare(strict_types=1);

/**
 * Runnable demo for the audit trail + idle-session timeout. No database needed.
 *
 *   php examples/demo.php
 */

require __DIR__ . '/../src/AuditLogRepository.php';
require __DIR__ . '/../src/SessionContext.php';
require __DIR__ . '/../src/ActionLogger.php';
require __DIR__ . '/../src/IdleTimeoutGuard.php';

use Portfolio\AuditSessionLog\ActionLogger;
use Portfolio\AuditSessionLog\ArrayAuditLogRepository;
use Portfolio\AuditSessionLog\IdleTimeoutGuard;
use Portfolio\AuditSessionLog\SessionContext;

// A controllable clock so the timeout is deterministic in the demo.
$now  = strtotime('2026-07-02 09:00:00');
$tick = function () use (&$now) { return $now; };

$repo = new ArrayAuditLogRepository(departments: [1 => 'Intake', 2 => 'Care team']);
$logger = new ActionLogger($repo, $tick);
$guard  = new IdleTimeoutGuard($logger, $tick, timeoutSeconds: 1800);

echo "== Actions logged with the acting department ==\n";
$session = new SessionContext([
    'user_id'       => 7,
    'department_id' => 1,            // acting for "Intake"
    'ip_address'    => '10.0.0.5',
    'last_activity' => $now,
]);
$logger->log($session, '/clients/view', clientId: 42);
$logger->log($session, '/clients/edit', clientId: 42);

// User switches to another department, then acts again.
$session2 = new SessionContext(['user_id' => 7, 'department_id' => 2, 'ip_address' => '10.0.0.5', 'last_activity' => $now]);
$logger->log($session2, '/reports/export');

echo "Overview (newest first, with department):\n";
foreach ($repo->recent(10) as $row) {
    printf("  user %d | %-9s | %-16s | client %s\n",
        $row['user_id'], $row['department'] ?? '—', $row['action'], $row['client_id'] ?? '-');
}

echo "\n== Active session is kept alive ==\n";
$session->touch($now - 5 * 60); // last active 5 minutes ago
$alive = $guard->check($session);
echo "check() after 5 min idle -> " . ($alive ? 'ACTIVE (refreshed)' : 'expired') . "\n";

echo "\n== Idle beyond 30 min -> audited timeout logout ==\n";
$session->touch($now - 31 * 60); // last active 31 minutes ago
$alive = $guard->check($session);
echo "check() after 31 min idle -> " . ($alive ? 'active' : 'EXPIRED (session cleared)') . "\n";
echo "session authenticated now? " . ($session->isAuthenticated() ? 'yes' : 'no') . "\n";

echo "\nAudit overview now includes the timeout event:\n";
foreach ($repo->recent(1) as $row) {
    printf("  user %d | %-9s | %s\n", $row['user_id'], $row['department'] ?? '—', $row['action']);
}
