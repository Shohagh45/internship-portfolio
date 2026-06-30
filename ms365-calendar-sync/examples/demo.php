<?php

declare(strict_types=1);

/**
 * Runnable demo for the Microsoft 365 / Graph calendar sync. Uses a fake HTTP
 * client that mimics the Microsoft token endpoint and Graph, so it needs no
 * Azure app, network, or credentials.
 *
 *   php examples/demo.php
 */

require __DIR__ . '/../src/HttpClient.php';
require __DIR__ . '/../src/TokenStore.php';
require __DIR__ . '/../src/GraphOAuthClient.php';
require __DIR__ . '/../src/GraphCalendarSync.php';

use Portfolio\Ms365CalendarSync\FakeHttpClient;
use Portfolio\Ms365CalendarSync\GraphCalendarSync;
use Portfolio\Ms365CalendarSync\GraphOAuthClient;
use Portfolio\Ms365CalendarSync\InMemoryTokenStore;

$config = [
    'tenant'        => 'common',
    'client_id'     => '00000000-app-id',
    'client_secret' => 'dummy-secret',
    'redirect_uri'  => 'https://app.example.com/agenda/ms_callback',
];

// Fake Microsoft endpoints: the token endpoint and a Graph create-event call.
$http = (new FakeHttpClient())
    ->on('/oauth2/v2.0/token', 200, [
        'access_token'  => 'access-token-1',
        'refresh_token' => 'refresh-token-1',
        'expires_in'    => 3600,
        'scope'         => 'Calendars.ReadWrite',
    ])
    ->on('/me/events', 201, ['id' => 'AAMkAGI2_graph_event_id']);

$oauth  = new GraphOAuthClient($config, $http);
$tokens = new InMemoryTokenStore();
$sync   = new GraphCalendarSync($oauth, $tokens, $http);

$userId = 7;

echo "== 1. Build the consent URL ==\n";
echo $oauth->getAuthorizeUrl(state: 'csrf-token-xyz') . "\n";

echo "\n== 2. Exchange the returned code for tokens ==\n";
$token = $oauth->exchangeCode('auth-code-from-redirect');
$tokens->save($userId, $token);
echo "Stored token, expires_at = " . date('H:i:s', $token['expires_at']) . " (access: {$token['access_token']})\n";

echo "\n== 3. Create a calendar event (POST /me/events) ==\n";
$result = $sync->upsertEvent($userId, [
    'title'    => 'Intake appointment',
    'note'     => "First meeting.\nBring ID.",
    'location' => 'Main office',
    'start'    => '2026-07-02 09:30',
    'end'      => '2026-07-02 10:15',
]);
echo "ok={$result['ok']}, ms_event_id={$result['ms_event_id']}\n";

echo "\n== 4. Re-save updates the same event (PATCH, no duplicate) ==\n";
$http->on('/me/events/AAMkAGI2_graph_event_id', 200, ['id' => 'AAMkAGI2_graph_event_id']);
$update = $sync->upsertEvent($userId, [
    'title'       => 'Intake appointment (rescheduled)',
    'start'       => '2026-07-02 11:00',
    'end'         => '2026-07-02 11:45',
    'ms_event_id' => $result['ms_event_id'],
]);
$patchCall = end($http->calls);
echo "method used: {$patchCall['method']} -> {$patchCall['url']}\n";
echo "ok={$update['ok']}, ms_event_id={$update['ms_event_id']}\n";

echo "\n== 5. Expired token is refreshed automatically ==\n";
$tokens->save($userId, [
    'access_token'  => 'stale',
    'refresh_token' => 'refresh-token-1',
    'expires_at'    => time() - 10, // already expired
]);
$fresh = $sync->getFreshAccessToken($userId);
echo "refreshed access token = {$fresh} (was 'stale')\n";
