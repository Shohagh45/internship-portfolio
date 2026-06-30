<?php

declare(strict_types=1);

namespace Portfolio\JiraHelpdesk;

/**
 * Minimal HTTP abstraction so the Jira client is testable without network
 * access. Production uses CurlHttpTransport; the demo/tests use a fake.
 */
interface HttpTransport
{
    /**
     * @param array<string,mixed>  $json     JSON body to send.
     * @param array{0:string,1:string} $basicAuth  [username, password/token].
     * @param array<string,string> $headers  Extra headers.
     * @return array{status:int,body:string}
     */
    public function postJson(string $url, array $json, array $basicAuth, array $headers = []): array;
}

/**
 * Real transport backed by cURL with HTTP Basic auth (Jira Cloud uses
 * email + API token as basic credentials).
 */
final class CurlHttpTransport implements HttpTransport
{
    public function postJson(string $url, array $json, array $basicAuth, array $headers = []): array
    {
        $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $key => $value) {
            $defaultHeaders[] = "{$key}: {$value}";
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);
        curl_setopt($ch, CURLOPT_USERPWD, $basicAuth[0] . ':' . $basicAuth[1]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));

        $body   = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body];
    }
}

/**
 * Fake transport for demos/tests: records the last request and returns a
 * canned response. No network, no credentials.
 */
final class FakeHttpTransport implements HttpTransport
{
    /** @var array{url:string,json:array,auth:array,headers:array}|null */
    public ?array $lastRequest = null;

    public function __construct(private int $status = 201, private string $body = '{"issueKey":"HELP-123","issueId":"10001"}')
    {
    }

    public function postJson(string $url, array $json, array $basicAuth, array $headers = []): array
    {
        $this->lastRequest = ['url' => $url, 'json' => $json, 'auth' => $basicAuth, 'headers' => $headers];
        return ['status' => $this->status, 'body' => $this->body];
    }
}
