<?php

declare(strict_types=1);

namespace Portfolio\Ms365CalendarSync;

use RuntimeException;

/**
 * Microsoft identity platform (Azure AD) OAuth2 authorization-code client.
 *
 * Handles the three token-related steps for delegated Microsoft Graph access:
 *   1. building the /authorize URL the user is redirected to,
 *   2. exchanging the returned code for access + refresh tokens,
 *   3. refreshing an expired access token.
 *
 * All app credentials come from config (sourced from env in the host app).
 */
final class GraphOAuthClient
{
    private const SCOPE = 'offline_access openid profile Calendars.ReadWrite';

    /**
     * @param array{tenant?:string,client_id:string,client_secret:string,redirect_uri:string} $config
     */
    public function __construct(
        private array $config,
        private HttpClient $http,
    ) {
    }

    private function authBase(): string
    {
        $tenant = $this->config['tenant'] ?? 'common';
        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0";
    }

    /** The URL to redirect the user to. `state` protects against CSRF. */
    public function getAuthorizeUrl(string $state): string
    {
        $params = [
            'client_id'     => $this->config['client_id'],
            'response_type' => 'code',
            'redirect_uri'  => $this->config['redirect_uri'],
            'response_mode' => 'query',
            'scope'         => self::SCOPE,
            'state'         => $state,
            // Reduce silent account-reuse loops on shared machines.
            'prompt'        => 'select_account',
        ];
        return $this->authBase() . '/authorize?' . http_build_query($params);
    }

    /** @return array<string,mixed> normalized token record */
    public function exchangeCode(string $code): array
    {
        return $this->tokenRequest([
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $this->config['redirect_uri'],
        ]);
    }

    /** @return array<string,mixed> normalized token record */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope'         => self::SCOPE,
        ]);
    }

    /**
     * @param array<string,string> $extra
     * @return array<string,mixed>
     */
    private function tokenRequest(array $extra): array
    {
        $fields = array_merge([
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ], $extra);

        $response = $this->http->postForm($this->authBase() . '/token', $fields);
        $data = json_decode($response['body'], true);

        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($data) || empty($data['access_token'])) {
            $reason = is_array($data) && isset($data['error_description']) ? $data['error_description'] : "HTTP {$response['status']}";
            throw new RuntimeException("Microsoft token request failed: {$reason}");
        }

        return [
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'scope'         => $data['scope'] ?? self::SCOPE,
            // Refresh 60s early to avoid edge-of-expiry failures.
            'expires_at'    => time() + (int)($data['expires_in'] ?? 3600) - 60,
        ];
    }
}
