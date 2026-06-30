# ICS Calendar Feed with Signed Tokens

Lets a user **subscribe** to their personal agenda from Outlook / Google /
Apple Calendar via a single URL, without exposing an enumerable user id and
without a login on every calendar refresh.

The challenge: a calendar client polls the feed URL unattended, so it can't
carry a session cookie. A naive `?user=42` feed would let anyone read anyone's
calendar by changing the number. The solution here is a **stateless, signed,
revocable token**.

## How it works

### Signed subscription token (`CalendarTokenSigner`)

- Each user has a random per-user **secret** (generated once, stored, revocable).
- The feed token is `base64url(userId) . "." . base64url(HMAC-SHA256(userId, secret))`.
- Validation recomputes the HMAC from the stored secret and compares with
  `hash_equals()` (constant-time, so it's not vulnerable to timing attacks).
- Because the signature is keyed to a **per-user** secret, `rotateSecret()`
  instantly invalidates every previously issued URL for that user — a real
  revocation story, not just "change one global key".
- The secret store is an interface, so it works with any database. An in-memory
  store is included for the demo.

### ICS output (`IcsFeedBuilder`)

Produces RFC 5545 `VCALENDAR` output that real clients accept:

- A `VTIMEZONE` block with explicit `TZID` (Europe/Amsterdam) so events show at
  the correct local time across DST — Outlook in particular mis-renders feeds
  that only send floating or UTC times.
- Proper line folding, `DTSTAMP`/`UID`/`SUMMARY`/`DTSTART`/`DTEND`, and text
  escaping per the spec.

## Usage

```php
use Portfolio\IcsCalendarFeed\CalendarTokenSigner;
use Portfolio\IcsCalendarFeed\IcsFeedBuilder;

$signer = new CalendarTokenSigner($secretStore);

// When the user clicks "Subscribe", hand them a URL containing the token:
$token = $signer->issueToken($userId);
$url   = "https://app.example.com/calendar/feed.ics?token={$token}";

// On each feed request (no session available):
$userId = $signer->validateToken($_GET['token'] ?? '');
if ($userId === null) {
    http_response_code(403);
    exit;
}
$events = $repository->upcomingEventsFor($userId);

header('Content-Type: text/calendar; charset=utf-8');
echo (new IcsFeedBuilder('Europe/Amsterdam'))->build($events);
```

## Run the demo

```bash
php examples/demo.php
```

It issues a token, validates it, shows that a tampered token is rejected, shows
that rotating the secret revokes the old token, and prints a sample ICS feed.

## Original context

Extracted from `ics.model.php` (token signing/validation) plus the ICS
generation that lived in the agenda controller. The original token logic is
preserved; the storage was decoupled behind a `SecretStore` interface and the
ICS builder was rewritten as a standalone class with neutral sample data.
