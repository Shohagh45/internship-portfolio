<?php

declare(strict_types=1);

namespace Portfolio\IcsCalendarFeed;

/**
 * Issues and validates stateless, signed, revocable tokens for personal
 * calendar-feed URLs.
 *
 * Token format:  base64url(userId) "." base64url(HMAC_SHA256(userId, secret))
 *
 * The signing key is a per-user secret held in a SecretStore, so rotating that
 * secret revokes every token previously issued to the user.
 */
final class CalendarTokenSigner
{
    public function __construct(private SecretStore $store)
    {
    }

    /** Issues a feed token for the user, creating the user's secret if needed. */
    public function issueToken(int $userId): string
    {
        $secret  = $this->getOrCreateSecret($userId);
        $message = (string)$userId;
        $sig     = hash_hmac('sha256', $message, $secret, true);

        return $this->b64UrlEncode($message) . '.' . $this->b64UrlEncode($sig);
    }

    /**
     * Validates a token and returns the user id, or null if invalid, tampered,
     * or revoked.
     */
    public function validateToken(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        $rawUser = $this->b64UrlDecode($parts[0]);
        if ($rawUser === '' || !ctype_digit($rawUser)) {
            return null;
        }
        $userId = (int)$rawUser;

        $secret = $this->store->getSecret($userId);
        if ($secret === null) {
            return null; // no secret on file, or it was revoked
        }

        $expected = hash_hmac('sha256', (string)$userId, $secret, true);
        $given    = $this->b64UrlDecode($parts[1]);

        // Constant-time comparison to avoid signature timing attacks.
        if (!hash_equals($expected, $given)) {
            return null;
        }

        return $userId;
    }

    /** Revokes all existing tokens for the user by replacing their secret. */
    public function rotateSecret(int $userId): void
    {
        $this->store->saveSecret($userId, $this->randomSecret());
    }

    private function getOrCreateSecret(int $userId): string
    {
        $secret = $this->store->getSecret($userId);
        if ($secret === null) {
            $secret = $this->randomSecret();
            $this->store->saveSecret($userId, $secret);
        }
        return $secret;
    }

    private function randomSecret(): string
    {
        return $this->b64UrlEncode(random_bytes(32));
    }

    private function b64UrlEncode(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private function b64UrlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad > 0) {
            $s .= str_repeat('=', 4 - $pad);
        }
        return (string)base64_decode(strtr($s, '-_', '+/'), true);
    }
}
