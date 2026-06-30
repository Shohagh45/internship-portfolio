<?php

declare(strict_types=1);

namespace Portfolio\IcsCalendarFeed;

/**
 * Storage abstraction for per-user calendar-feed secrets. Implement this against
 * any database. A secret is created once per user and can be rotated to revoke
 * every token previously issued to that user.
 */
interface SecretStore
{
    /** Returns the active secret for the user, or null if none / revoked. */
    public function getSecret(int $userId): ?string;

    /** Stores (creating or replacing) the active secret for the user. */
    public function saveSecret(int $userId, string $secret): void;
}

/**
 * Simple in-memory store, used by the demo and tests.
 */
final class InMemorySecretStore implements SecretStore
{
    /** @var array<int,string> */
    private array $secrets = [];

    public function getSecret(int $userId): ?string
    {
        return $this->secrets[$userId] ?? null;
    }

    public function saveSecret(int $userId, string $secret): void
    {
        $this->secrets[$userId] = $secret;
    }
}
