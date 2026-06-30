# RBAC Access Control

Role-based access control that decides whether a **role** may perform an
**action** (create/read/update/delete) on a **feature**. It backs the
permission checks scattered through a case-management app's controllers and UI.

## What it shows

- **Deny by default** — a decision requires an explicit grant for
  `(feature, action, role)`. No grant, unknown feature, or unknown action all
  **fail closed**.
- **Tolerant name resolution** — features/actions can be referenced by id or by
  name; names match case-insensitively and then by a normalized form that
  ignores spaces, hyphens, underscores and casing (`"Social Map"`,
  `"social-map"`, `"socialmap"` all resolve to the same feature).
- **Action synonyms** — English and Dutch verbs (`edit`/`wijzigen`,
  `add`/`aanmaken`, `view`/`inzien`, …) map onto canonical CRUD actions, so call
  sites don't have to use the exact stored verb.
- **Environment kill-switch** — `AccessControl::fromEnv()` reads an
  `ACL_DISABLED` flag so enforcement can be bypassed in a controlled
  dev/demo environment without code changes.
- **Clear boolean** — exposes `isAllowed()` (true = permitted). The original
  method returned true to mean *blocked*; that inverted logic was error-prone,
  so the inversion is documented and `isBlocked()` is provided for legacy
  parity.

## Components

| Class | Responsibility |
|---|---|
| `AccessControl` | The decision: synonyms, kill-switch, deny-by-default |
| `PermissionRepository` | Resolve names→ids and look up grants (your DB) |
| `ArrayPermissionRepository` | In-memory implementation for the demo/tests |

## Usage

```php
$acl = AccessControl::fromEnv(new DbPermissionRepository($pdo));

if (!$acl->isAllowed('Clients', 'update', $currentRoleId)) {
    return $this->denied();
}
```

## Run the demo

```bash
php examples/demo.php
```

It allows/denies actions for a "case worker" role, demonstrates normalized name
and synonym resolution, shows an unknown feature failing closed, and shows the
`ACL_DISABLED` kill-switch allowing everything.

## Original context

Extracted from `acl.model.php`. The original queried `rechten`,
`functionaliteiten` and `crud` tables directly and used the inverted
"true = block" convention; here the data access sits behind a
`PermissionRepository` interface and the boolean is normalized to
"true = allow". The `ACL_DISABLED` toggle and the tolerant name/synonym matching
are preserved from the original.
