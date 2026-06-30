<?php

declare(strict_types=1);

namespace Portfolio\Ms365CalendarSync;

/**
 * Persists a user's OAuth tokens. Implement against any database.
 *
 * A token record is an associative array:
 *   ['access_token' => string, 'refresh_token' => string,
 *    'expires_at' => int (unix ts), 'scope' => string]
 */
interface TokenStore
{
    /** @return array<string,mixed>|null */
    public function get(int $userId): ?array;

    /** @param array<string,mixed> $token */
    public function save(int $userId, array $token): void;

    public function delete(int $userId): void;
}

final class InMemoryTokenStore implements TokenStore
{
    /** @var array<int,array<string,mixed>> */
    private array $tokens = [];

    public function get(int $userId): ?array
    {
        return $this->tokens[$userId] ?? null;
    }

    public function save(int $userId, array $token): void
    {
        // Preserve the existing refresh token if a refresh response omits it
        // (Microsoft does not always return a new one).
        if (empty($token['refresh_token']) && isset($this->tokens[$userId]['refresh_token'])) {
            $token['refresh_token'] = $this->tokens[$userId]['refresh_token'];
        }
        $this->tokens[$userId] = $token;
    }

    public function delete(int $userId): void
    {
        unset($this->tokens[$userId]);
    }
}
