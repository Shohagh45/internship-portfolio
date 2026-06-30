<?php

declare(strict_types=1);

namespace Portfolio\Ms365CalendarSync;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

/**
 * Pushes the app's own calendar appointments into a user's Microsoft 365
 * calendar via Microsoft Graph (`/me/events`), keeping a stored Graph event id
 * so later saves update the same event instead of creating duplicates.
 *
 * Token freshness (including silent refresh) is handled transparently before
 * each Graph call.
 */
final class GraphCalendarSync
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private GraphOAuthClient $oauth,
        private TokenStore $tokens,
        private HttpClient $http,
        private string $timezone = 'Europe/Amsterdam',
    ) {
    }

    /**
     * Returns a valid access token for the user, refreshing if expired. Returns
     * null when the user isn't connected or the refresh token is gone (the
     * caller should then prompt the user to reconnect).
     */
    public function getFreshAccessToken(int $userId): ?string
    {
        $record = $this->tokens->get($userId);
        if ($record === null) {
            return null;
        }

        $expired = empty($record['expires_at']) || (int)$record['expires_at'] <= time();
        if (!$expired) {
            return (string)$record['access_token'];
        }

        if (empty($record['refresh_token'])) {
            return null;
        }

        $refreshed = $this->oauth->refresh((string)$record['refresh_token']);
        $this->tokens->save($userId, $refreshed);
        return (string)$refreshed['access_token'];
    }

    /**
     * Creates or updates the Graph event for a local appointment.
     *
     * @param array{
     *     title?:string, note?:string, location?:string,
     *     start:DateTimeInterface|string, end:DateTimeInterface|string,
     *     ms_event_id?:?string
     * } $event
     * @return array{ok:bool, ms_event_id:?string, error:?string}
     */
    public function upsertEvent(int $userId, array $event): array
    {
        try {
            $payload = $this->buildEventPayload($event);

            if (!empty($event['ms_event_id'])) {
                $this->graph($userId, 'PATCH', '/me/events/' . rawurlencode($event['ms_event_id']), $payload);
                return ['ok' => true, 'ms_event_id' => $event['ms_event_id'], 'error' => null];
            }

            $created = $this->graph($userId, 'POST', '/me/events', $payload);
            return ['ok' => true, 'ms_event_id' => $created['id'] ?? null, 'error' => null];
        } catch (RuntimeException $e) {
            // Surface the error so the caller can record it on the local event.
            return ['ok' => false, 'ms_event_id' => $event['ms_event_id'] ?? null, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok:bool, error:?string} */
    public function deleteEvent(int $userId, string $msEventId): array
    {
        try {
            $this->graph($userId, 'DELETE', '/me/events/' . rawurlencode($msEventId));
            return ['ok' => true, 'error' => null];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    private function buildEventPayload(array $event): array
    {
        $tz    = new DateTimeZone($this->timezone);
        $start = $this->toLocal($event['start'], $tz);
        $end   = $this->toLocal($event['end'], $tz);
        if ($end <= $start) {
            $end = $start->modify('+60 minutes');
        }

        $payload = [
            'subject' => $event['title'] ?? 'Appointment',
            'body'    => ['contentType' => 'HTML', 'content' => nl2br((string)($event['note'] ?? ''))],
            'start'   => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => $this->timezone],
            'end'     => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => $this->timezone],
        ];
        if (!empty($event['location'])) {
            $payload['location'] = ['displayName' => (string)$event['location']];
        }
        return $payload;
    }

    private function toLocal(DateTimeInterface|string $value, DateTimeZone $tz): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone($tz);
        }
        return new DateTimeImmutable($value, $tz);
    }

    /**
     * @param array<string,mixed>|null $json
     * @return array<string,mixed>
     */
    private function graph(int $userId, string $method, string $path, ?array $json = null): array
    {
        $token = $this->getFreshAccessToken($userId);
        if ($token === null) {
            throw new RuntimeException('No valid Microsoft Graph token; user must reconnect.');
        }

        $response = $this->http->request($method, self::GRAPH_BASE . $path, [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ], $json);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $data = json_decode($response['body'], true);
            $msg  = $data['error']['message'] ?? "HTTP {$response['status']}";
            throw new RuntimeException("Graph API error: {$msg}");
        }

        $data = $response['body'] !== '' ? json_decode($response['body'], true) : [];
        return is_array($data) ? $data : [];
    }
}
