# Microsoft 365 / Graph Calendar Sync

Two-way bridge between an app's internal agenda and a user's **Microsoft 365
calendar**: the user connects their account once via OAuth2, and afterwards
appointments saved in the app are pushed into Outlook/Microsoft 365 through
Microsoft Graph.

This is the OAuth + Graph half of the calendar feature (the read-only
`.ics` subscription half is a separate module, [ics-calendar-feed](../ics-calendar-feed)).

## What it shows

- **OAuth2 authorization-code flow** against the Microsoft identity platform
  (`login.microsoftonline.com`): consent URL with `state` (CSRF), code→token
  exchange, and **silent refresh** of expired access tokens using the stored
  refresh token.
- **Token lifecycle handling** — tokens are stored per user with an
  `expires_at`, refreshed 60s early, and the refresh token is preserved when
  Microsoft's refresh response omits a new one (a real-world gotcha).
- **Idempotent Graph upsert** — a created event's Graph id is stored, so the
  next save issues a `PATCH /me/events/{id}` instead of creating a duplicate.
- **Correct timezones** — events are sent with explicit local `dateTime` +
  `timeZone`, and a zero/negative duration is defended with a 60-minute default.
- **Testable transport** — all HTTP goes through an `HttpClient` interface, so
  the whole flow runs offline against a fake Microsoft.

## Components

| Class | Responsibility |
|---|---|
| `GraphOAuthClient` | Build consent URL, exchange code, refresh tokens |
| `TokenStore` (+ `InMemoryTokenStore`) | Persist per-user tokens |
| `GraphCalendarSync` | Fresh-token resolution, create/update/delete `/me/events` |
| `HttpClient` (+ `FakeHttpClient`) | HTTP abstraction for offline testing |

## Usage

```php
$oauth = new GraphOAuthClient([
    'tenant'        => getenv('MS_TENANT') ?: 'common',
    'client_id'     => getenv('MS_CLIENT_ID'),
    'client_secret' => getenv('MS_CLIENT_SECRET'),
    'redirect_uri'  => getenv('MS_REDIRECT_URI'),
], new CurlHttpClient());

// 1. Redirect the user:
header('Location: ' . $oauth->getAuthorizeUrl($state));

// 2. In the redirect handler:
$token = $oauth->exchangeCode($_GET['code']);
$tokenStore->save($userId, $token);

// 3. Whenever an appointment is saved:
$sync = new GraphCalendarSync($oauth, $tokenStore, new CurlHttpClient());
$result = $sync->upsertEvent($userId, [
    'title' => $event->title,
    'start' => $event->start,   // DateTimeInterface or 'Y-m-d H:i'
    'end'   => $event->end,
    'ms_event_id' => $event->msEventId, // null on first sync
]);
// persist $result['ms_event_id'] back onto the local event
```

(A `CurlHttpClient implements HttpClient` is a few lines of cURL — omitted here
to keep the module dependency-free; the demo uses `FakeHttpClient`.)

## Run the demo

```bash
php examples/demo.php
```

It builds the consent URL, exchanges a code, creates an event, shows that
re-saving issues a `PATCH` to the same event id (no duplicate), and that an
expired token is refreshed automatically.

## Original context

Extracted from `msgraph.model.php`. The original stored tokens in a
`t_ms_tokens` table and the Graph event id on the agenda row; here storage sits
behind a `TokenStore` interface and the HTTP layer behind `HttpClient`. App- and
tenant-specific values, credential handling, and Dutch field names were removed
or generalized. **No real client id, secret, tenant, or token is included.**
