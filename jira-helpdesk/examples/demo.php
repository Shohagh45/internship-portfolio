<?php

declare(strict_types=1);

/**
 * Runnable demo for the Jira Service Desk helpdesk client. Uses a fake HTTP
 * transport, so it needs no Jira instance, network, or credentials.
 *
 *   php examples/demo.php
 */

require __DIR__ . '/../src/HttpTransport.php';
require __DIR__ . '/../src/JiraServiceDeskClient.php';

use Portfolio\JiraHelpdesk\FakeHttpTransport;
use Portfolio\JiraHelpdesk\JiraServiceDeskClient;

// In the host app these come from environment variables; here they are dummies.
$config = [
    'base_url'        => 'https://example.atlassian.net',
    'email'           => 'helpdesk-bot@example.com',
    'api_token'       => 'dummy-token-not-real',
    'service_desk_id' => '1',
    'request_type_id' => '10',
];

echo "== Missing config is rejected ==\n";
$empty = new JiraServiceDeskClient(['base_url' => '', 'email' => '', 'api_token' => '', 'service_desk_id' => '', 'request_type_id' => ''], new FakeHttpTransport());
echo 'isConfigured: ' . var_export($empty->isConfigured(), true) . " (expected false)\n";

echo "\n== Create a helpdesk request ==\n";
$fake   = new FakeHttpTransport(status: 201, body: '{"issueKey":"HELP-457","issueId":"10042"}');
$client = new JiraServiceDeskClient($config, $fake);

$issueKey = $client->createHelpdeskRequest(
    category: 'Account access',
    priority: 'High',
    message: '<p>I cannot log in after the password reset.</p>',
    userName: 'Jane Doe',
    userEmail: 'jane@example.com'
);

echo "Returned issue key: {$issueKey}\n\n";
echo "Posted to: {$fake->lastRequest['url']}\n";
echo "Payload sent:\n";
echo json_encode($fake->lastRequest['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n== A 4xx/5xx response raises a clean error ==\n";
$failing = new JiraServiceDeskClient($config, new FakeHttpTransport(status: 403, body: '{"errorMessage":"Forbidden"}'));
try {
    $failing->createRequest('Test', 'Body');
} catch (RuntimeException $e) {
    echo 'Caught: ' . $e->getMessage() . "\n";
}
