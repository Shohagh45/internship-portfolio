# Jira Service Desk Helpdesk Client

Turns an in-app "contact support" / FAQ form into a real ticket by creating a
**Jira Service Management (Service Desk) request** over the REST API. Built so
that helpdesk submissions from end users land directly in the support team's
queue instead of an inbox.

## Design

- **Service Desk endpoint** — posts to `/rest/servicedeskapi/request` with a
  `serviceDeskId` + `requestTypeId`, so tickets arrive as proper customer
  requests (with the right request type and SLA), not bare issues.
- **Config from environment** — base URL, account email, API token, and the
  desk/request-type ids all come from config sourced from env vars. Nothing is
  hard-coded, and `isConfigured()` fails closed if anything is missing.
- **Injected HTTP transport** — the network layer sits behind an
  `HttpTransport` interface (`CurlHttpTransport` for production, with HTTP Basic
  auth = email + API token as Jira Cloud expects). This makes the payload and
  error handling fully testable offline with a fake transport.
- **Form-to-ticket helper** — `createHelpdeskRequest()` assembles a readable
  summary and description from raw form fields (category, priority, message,
  submitter), stripping HTML and truncating the summary sensibly.
- **Clean failures** — any non-2xx response or malformed body becomes a single
  `RuntimeException`, so the calling code never has to parse cURL internals.

## Usage

```php
use Portfolio\JiraHelpdesk\JiraServiceDeskClient;
use Portfolio\JiraHelpdesk\CurlHttpTransport;

$client = new JiraServiceDeskClient([
    'base_url'        => getenv('JIRA_BASE_URL'),
    'email'           => getenv('JIRA_EMAIL'),
    'api_token'       => getenv('JIRA_API_TOKEN'),
    'service_desk_id' => getenv('JIRA_SERVICE_DESK_ID'),
    'request_type_id' => getenv('JIRA_REQUEST_TYPE_ID'),
], new CurlHttpTransport());

$issueKey = $client->createHelpdeskRequest(
    category: 'Account access',
    priority: 'High',
    message: $form['message'],
    userName: $user->name,
    userEmail: $user->email
);
// -> "HELP-457"
```

## Run the demo

```bash
php examples/demo.php
```

It rejects a missing config, creates a request through the fake transport
(printing the exact JSON payload sent to Jira), and shows a 403 response turned
into a clean exception.

## Original context

Extracted from `jira.model.php`. The original supported both the classic
`/rest/api/2/issue` endpoint and the Service Desk endpoint, plus file
attachments via `CURLFile`. This portfolio version keeps the Service Desk path
and the form-to-ticket builder, decouples the HTTP transport behind an
interface, removes credential logging, and uses neutral English field labels in
place of the app-specific localized ones.
