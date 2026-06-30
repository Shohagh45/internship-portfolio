# Internship Portfolio — Backend & AI Modules

Selected, self-contained modules I designed and built during a software
engineering internship on a PHP/MySQL case-management platform. The originals
live in a private company codebase; the versions here are **clean-room
extractions**: rewritten to be framework-agnostic, stripped of all
company-specific data, credentials, and personal data, and reduced to runnable
demos.

> Permission to publish was obtained from the company. No proprietary business
> logic, customer data, schema, or secrets are included — only the reusable
> engineering patterns I authored.

## Modules

| Module | What it demonstrates | Highlights |
|---|---|---|
| [guarded-export](./guarded-export) | Defensive backend engineering | Double-submit locking, size pre-flight guard, validation, graceful errors |
| [ics-calendar-feed](./ics-calendar-feed) | Security / applied crypto | HMAC-signed, per-user, revocable calendar subscription tokens + RFC 5545 ICS output |
| [faq-chatbot](./faq-chatbot) | Applied AI / retrieval | Embedding-based FAQ retrieval, cosine similarity, confidence thresholds & suggestions |
| [jira-helpdesk](./jira-helpdesk) | API integration | In-app form → Jira Service Desk ticket over REST, testable transport, env-based config |

Each module has its own README, a `src/` with the core class(es), and an
`examples/` script you can run directly.

## Running the demos

All demos are plain PHP and need **no API keys, database, or framework**:

```bash
php guarded-export/examples/demo.php
php ics-calendar-feed/examples/demo.php
php faq-chatbot/examples/demo.php
php jira-helpdesk/examples/demo.php
```

(PHP 8.0+ recommended.)

## Context

These were part of a ~3.5 month internship contributing ~57 commits across an
internal case-management system: export tooling, a Microsoft 365 / ICS calendar
sync, a Jira Service Desk helpdesk integration, and an FAQ chatbot. The three
modules here are the most self-contained and the clearest to show in isolation.

## License

MIT — see [LICENSE](./LICENSE).
