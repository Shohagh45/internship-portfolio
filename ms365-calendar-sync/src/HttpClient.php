<?php

declare(strict_types=1);

namespace Portfolio\Ms365CalendarSync;

/**
 * Small HTTP abstraction covering the two shapes the Microsoft endpoints need:
 * form-encoded POSTs to the OAuth token endpoint, and JSON requests to Microsoft
 * Graph. Injecting it keeps the OAuth/sync logic testable without network access.
 */
interface HttpClient
{
    /**
     * @param array<string,string> $fields
     * @return array{status:int,body:string}
     */
    public function postForm(string $url, array $fields): array;

    /**
     * @param array<string,string> $headers
     * @param array<string,mixed>|null $json
     * @return array{status:int,body:string}
     */
    public function request(string $method, string $url, array $headers = [], ?array $json = null): array;
}

/**
 * Programmable fake used by the demo/tests. Responses are keyed by a substring
 * that must appear in the request URL; the first match wins.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var array<int,array{needle:string,status:int,body:string}> */
    private array $routes = [];

    /** @var array<int,array{method:string,url:string,headers:array,body:mixed}> */
    public array $calls = [];

    public function on(string $urlNeedle, int $status, array|string $body): self
    {
        $this->routes[] = [
            'needle' => $urlNeedle,
            'status' => $status,
            'body'   => is_array($body) ? json_encode($body) : $body,
        ];
        return $this;
    }

    public function postForm(string $url, array $fields): array
    {
        $this->calls[] = ['method' => 'POST(form)', 'url' => $url, 'headers' => [], 'body' => $fields];
        return $this->match($url);
    }

    public function request(string $method, string $url, array $headers = [], ?array $json = null): array
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $json];
        return $this->match($url);
    }

    private function match(string $url): array
    {
        foreach ($this->routes as $route) {
            if (str_contains($url, $route['needle'])) {
                return ['status' => $route['status'], 'body' => $route['body']];
            }
        }
        return ['status' => 404, 'body' => '{"error":{"message":"no fake route matched"}}'];
    }
}
