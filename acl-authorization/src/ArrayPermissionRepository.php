<?php

declare(strict_types=1);

namespace Portfolio\AclAuthorization;

/**
 * In-memory PermissionRepository for the demo/tests. It also illustrates the
 * tolerant name resolution from the original: an exact case-insensitive match
 * first, then a normalized match that ignores spaces, hyphens, underscores and
 * casing (so "Social Map", "social-map" and "socialmap" all resolve).
 */
final class ArrayPermissionRepository implements PermissionRepository
{
    /**
     * @param array<string,int> $features  name => id
     * @param array<string,int> $actions   name => id
     * @param array<int,array{0:int,1:int,2:int}> $grants  list of [featureId, actionId, roleId]
     */
    public function __construct(
        private array $features,
        private array $actions,
        private array $grants,
    ) {
    }

    public function resolveFeatureId(string $feature): ?int
    {
        return $this->resolve($feature, $this->features);
    }

    public function resolveActionId(string $action): ?int
    {
        return $this->resolve($action, $this->actions);
    }

    public function hasGrant(int $featureId, int $actionId, int $roleId): bool
    {
        foreach ($this->grants as $grant) {
            if ($grant === [$featureId, $actionId, $roleId]) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,int> $map */
    private function resolve(string $name, array $map): ?int
    {
        if (is_numeric($name)) {
            return (int)$name;
        }

        // 1) Exact, case-insensitive.
        foreach ($map as $key => $id) {
            if (strcasecmp($key, $name) === 0) {
                return $id;
            }
        }

        // 2) Normalized: alphanumerics only, lower-cased.
        $target = $this->normalize($name);
        foreach ($map as $key => $id) {
            if ($this->normalize($key) === $target) {
                return $id;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $value) ?? '');
    }
}
