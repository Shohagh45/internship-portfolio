<?php

declare(strict_types=1);

namespace Portfolio\JiraHelpdesk;

use RuntimeException;

/**
 * Creates Jira Service Management (Service Desk) requests over the REST API so
 * that in-app helpdesk submissions land as tickets for the support team.
 *
 * Credentials and IDs come from config (sourced from environment variables in
 * the host app) — never hard-coded. The HTTP layer is injected so the payload
 * and decision logic can be tested without network or real credentials.
 */
final class JiraServiceDeskClient
{
    /**
     * @param array{
     *     base_url:string, email:string, api_token:string,
     *     service_desk_id:string, request_type_id:string
     * } $config
     */
    public function __construct(
        private array $config,
        private HttpTransport $http,
    ) {
    }

    /** True only when every required setting is present. */
    public function isConfigured(): bool
    {
        foreach (['base_url', 'email', 'api_token', 'service_desk_id', 'request_type_id'] as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Creates a Service Desk request and returns the new issue key (e.g. "HELP-123").
     *
     * @param array<string,mixed> $extraFields Additional requestFieldValues.
     */
    public function createRequest(string $summary, string $description, array $extraFields = []): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Jira Service Desk is not configured.');
        }

        $payload = [
            'serviceDeskId' => $this->config['service_desk_id'],
            'requestTypeId' => $this->config['request_type_id'],
            'requestFieldValues' => array_merge([
                'summary' => $summary,
                'description' => $description,
            ], $extraFields),
        ];

        $url = rtrim($this->config['base_url'], '/') . '/rest/servicedeskapi/request';

        $response = $this->http->postJson(
            $url,
            $payload,
            [$this->config['email'], $this->config['api_token']]
        );

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException("Jira request failed with HTTP {$response['status']}.");
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data) || empty($data['issueKey'])) {
            throw new RuntimeException('Jira response did not contain an issueKey.');
        }

        return (string)$data['issueKey'];
    }

    /**
     * Convenience builder for a helpdesk form submission: assembles a readable
     * summary and description from form fields, then creates the request.
     */
    public function createHelpdeskRequest(
        string $category,
        string $priority,
        string $message,
        string $userName,
        string $userEmail
    ): string {
        $plain   = trim(strip_tags($message));
        $summary = sprintf('Helpdesk - %s - %s', $category, $this->truncate($plain, 70));

        $description = implode("\n", [
            "Submitted by: {$userName} ({$userEmail})",
            "Category: {$category}",
            "Priority: {$priority}",
            '',
            'Request:',
            $plain,
        ]);

        return $this->createRequest($summary, $description);
    }

    private function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }
}
