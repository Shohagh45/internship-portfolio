<?php

declare(strict_types=1);

namespace Portfolio\AclAuthorization;

/**
 * Data source for access-control decisions. Implement against any database.
 *
 * Resolving names to ids is split out so the host app can map human-friendly
 * names ("Clients", "edit") to the numeric ids used in the grants table, with
 * its own indexes/caching.
 */
interface PermissionRepository
{
    /** Resolve a feature name (or numeric id) to its id, or null if unknown. */
    public function resolveFeatureId(string $feature): ?int;

    /** Resolve an action name (or numeric id) to its id, or null if unknown. */
    public function resolveActionId(string $action): ?int;

    /** True if an explicit grant exists for this (feature, action, role). */
    public function hasGrant(int $featureId, int $actionId, int $roleId): bool;
}
