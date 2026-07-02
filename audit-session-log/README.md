# Audit Trail + Idle Session Timeout

Records **who did what, from where, and — importantly — on behalf of which
department**, and ties in an idle-session timeout that is itself audited.

In a multi-department case-management system a single user can act under
different departments (participants) within one session. For accountability the
audit trail has to capture the department **at the moment of each action**, not
just the user. This module does that, and adds a 30-minute idle timeout that
logs a `session_timeout_logout` entry before ending the session — so automatic
logouts appear in the same overview as manual actions.

## What it shows

- **Context-capturing audit log** — every action records user, IP, timestamp,
  action, optional related client, and the acting `department_id` pulled from
  the session at write time.
- **Department-aware overviews** — reads resolve the department id to a name
  (a `LEFT JOIN` in the real query), and gracefully show blank for older rows
  written before the department column existed (nullable, backward compatible).
- **Idle timeout with audit** — `IdleTimeoutGuard` expires sessions after a
  configurable idle window, writing an audited timeout event **before** clearing
  the session, so you can see *why* a session ended.
- **Testable time** — the clock is injected, so timeout behaviour is
  deterministic in tests/the demo instead of depending on wall-clock sleeps.

## Components

| Class | Responsibility |
|---|---|
| `ActionLogger` | Write audit rows (from a session, or a system event) |
| `IdleTimeoutGuard` | Expire idle sessions and audit the logout |
| `SessionContext` | Read/refresh/clear the session fields these need |
| `AuditLogRepository` (+ `ArrayAuditLogRepository`) | Persist & read audit rows |

## Usage

```php
$logger = new ActionLogger($auditRepo, fn() => time());

// In the controller base, on each action:
$logger->log($session, $requestedRoute, $relatedClientId);

// Early in the request lifecycle:
$guard = new IdleTimeoutGuard($logger, fn() => time(), timeoutSeconds: 1800);
if (!$guard->check($session)) {
    redirect('/login?timeout=1');
}
```

## Run the demo

```bash
php examples/demo.php
```

It logs actions under two different departments, prints the department-aware
overview, keeps an active session alive, then drives an idle session past the
timeout and shows the audited `session_timeout_logout` appearing in the trail.

## Original context

Extracted from the audit/department-tracking work: the `actionLog()` controller
helper and `log_m->addAction()` model (which added an `fk_participant` column to
the action-log table and joined the participants table in the overviews), plus
the router's 30-minute idle timeout that logs a timeout event before clearing
the session. Schema, table names, Dutch labels, and framework specifics are
generalized; storage and time are behind interfaces/closures.
