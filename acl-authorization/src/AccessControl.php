<?php

declare(strict_types=1);

namespace Portfolio\AclAuthorization;

/**
 * Role-based access control: decides whether a role may perform an action on a
 * feature. A decision needs an explicit grant — anything not granted is denied
 * (deny-by-default / fail-closed).
 *
 * Features and actions can be passed by numeric id or by name; names are
 * resolved (with normalization and CRUD synonyms) via the repository.
 *
 * Note on the original: the legacy method `isToegestaan()` returned true to mean
 * "block access" (true = no explicit right). That inverted boolean was a
 * frequent source of confusion, so this version exposes a clear `isAllowed()`
 * (true = permitted) and keeps `isBlocked()` for parity with old call sites.
 */
final class AccessControl
{
    /** Common action synonyms (English + Dutch) mapped to canonical CRUD verbs. */
    private const ACTION_SYNONYMS = [
        'create' => 'create', 'aanmaken' => 'create', 'nieuw' => 'create', 'add' => 'create',
        'read'   => 'read',   'inzien'   => 'read',   'bekijken' => 'read', 'view' => 'read',
        'update' => 'update', 'wijzigen' => 'update', 'edit' => 'update',
        'delete' => 'delete', 'verwijderen' => 'delete', 'remove' => 'delete',
    ];

    public function __construct(
        private PermissionRepository $repo,
        /** Global kill-switch — when true, everything is allowed (e.g. dev/demo). */
        private bool $disabled = false,
    ) {
    }

    /**
     * Reads the kill-switch from an environment variable, so ACL can be
     * bypassed in a controlled environment without code changes.
     */
    public static function fromEnv(PermissionRepository $repo, string $envVar = 'ACL_DISABLED'): self
    {
        $raw = $_ENV[$envVar] ?? getenv($envVar) ?: '';
        $disabled = $raw !== '' && filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        return new self($repo, $disabled);
    }

    public function isAllowed(string $feature, string $action, int $roleId): bool
    {
        if ($this->disabled) {
            return true;
        }

        $featureId = $this->repo->resolveFeatureId($feature);
        $actionId  = $this->resolveAction($action);

        // Unknown feature or action -> deny by default.
        if ($featureId === null || $actionId === null) {
            return false;
        }

        return $this->repo->hasGrant($featureId, $actionId, $roleId);
    }

    /** Inverse of isAllowed(), for legacy "if blocked then stop" call sites. */
    public function isBlocked(string $feature, string $action, int $roleId): bool
    {
        return !$this->isAllowed($feature, $action, $roleId);
    }

    private function resolveAction(string $action): ?int
    {
        $direct = $this->repo->resolveActionId($action);
        if ($direct !== null) {
            return $direct;
        }
        $canonical = self::ACTION_SYNONYMS[strtolower(trim($action))] ?? null;
        return $canonical !== null ? $this->repo->resolveActionId($canonical) : null;
    }
}
