# Internship Contributions — Full Scope

This document summarizes the work I did during the internship on an internal
case-management platform (PHP/MySQL, custom MVC + Laravel components). It covers
**~57 commits across 8 feature areas** over roughly 3.5 months.

The repository's runnable modules are clean-room extractions of the most
self-contained pieces; this file records the full breadth, including work that
isn't broken out as a standalone demo. No proprietary code, data, or secrets are
included here — only descriptions.

| # | Area | Runnable module here |
|---|------|----------------------|
| 1 | Admin (Beheer) controller & model | [config-driven-crud](./config-driven-crud) |
| 2 | Guarded data export | [guarded-export](./guarded-export) |
| 3 | Agenda data model & defensive DB guards | — |
| 4 | Microsoft 365 / Graph calendar sync | [ms365-calendar-sync](./ms365-calendar-sync) |
| 4 | ICS calendar subscription feed | [ics-calendar-feed](./ics-calendar-feed) |
| 5 | Access control (RBAC) & search | [acl-authorization](./acl-authorization) |
| 5 | Audit trail (department tracking) + idle-session timeout | [audit-session-log](./audit-session-log) |
| 6 | Jira Service Desk helpdesk integration | [jira-helpdesk](./jira-helpdesk) |
| 7 | FAQ chatbot | [faq-chatbot](./faq-chatbot) |
| 8 | Department rights seeding, config toggles | — |

## 1. Admin (Beheer) controller & model
Built the back-office admin module (controller + model, ~2,000 lines) that
manages the system's many lookup lists (labels, products, categories, contact
types, statuses, reasons, …). Rather than a bespoke controller/model per list, I
drove them from a **data dictionary**: each entity is described once (table, id,
per-operation columns, defaults, audit messages) and a single generic engine
performs list/create/update/delete for all of them — with input whitelisting so
only configured columns are ever written. → the pattern is extracted as
**config-driven-crud**.

## 2. Guarded data export
Hardened the Excel export path: a guard that prevents double-submits with a
short session lock, pre-flights the payload size to reject runaway exports, and
renders friendly errors instead of raw SQL/memory failures. Included fixing the
underlying report queries (column qualification, correct date columns).
→ extracted as **guarded-export**.

## 3. Agenda data model & defensive DB guards
Added the agenda tables (`locations`, `events`, `event↔client` link) and, just
as importantly, made every agenda operation **degrade gracefully when the tables
or columns are missing** (no fatal `42S02` errors) — so the feature could ship
incrementally across environments at different migration states.

## 4. Microsoft 365 / Graph calendar sync + ICS feed
The largest feature. Two complementary halves:
- **Write sync** — OAuth2 (auth-code + refresh) against the Microsoft identity
  platform, then pushing appointments into the user's Microsoft 365 calendar via
  Graph `/me/events`, with idempotent create/update and per-event sync status.
  → extracted as **ms365-calendar-sync**.
- **Read feed** — a per-user, HMAC-signed, revocable `.ics` subscription URL
  with a proper `VTIMEZONE` block so Outlook/Google/Apple render correct local
  times. → extracted as **ics-calendar-feed**.

## 5. Access control (RBAC) & search
- **RBAC** — role/feature/action permission checks with tolerant name
  resolution and CRUD synonyms, deny-by-default, and an `ACL_DISABLED`
  environment kill-switch for controlled environments.
  → extracted as **acl-authorization**.
- **Search** — a global keyword search wired from the sidebar/menu and home
  page, supporting GET/POST with input persistence.

## 5b. Audit trail (department tracking) + idle-session timeout
Extended the action-audit log to capture **which department (participant)** a
user was acting under for each action — adding an `fk_participant` column, wiring
it through the `actionLog()` controller helper and logger model, and joining the
participants table into the audit overviews so each action shows its department.
Paired with a **30-minute idle-session timeout** that writes a
`session_timeout_logout` audit entry before clearing the session, so automatic
logouts are visible alongside manual actions. → extracted as
**audit-session-log**.

## 6. Jira Service Desk helpdesk integration
Connected the in-app FAQ/helpdesk form to **Jira Service Management** over REST:
form submissions become Service Desk requests (with attachments), with
structured server-side logging of each call. → extracted as **jira-helpdesk**.

## 7. FAQ chatbot
An embedding-based FAQ assistant: per-tenant FAQ store with pre-computed
embeddings, cosine-similarity retrieval, confidence thresholds (answer vs.
"did you mean…" suggestions vs. logged miss), bilingual (NL/EN) handling, a
greeting short-circuit, and request-id error logging. A front-end widget posts
to a public endpoint that bypasses the app login. → retrieval core extracted as
**faq-chatbot**.

## 8. Department rights seeding & config toggles
Seeding of default rights when a new department is added, plus operational
configuration toggles (e.g. the ACL kill-switch above) and supporting docs.

---

### Notes on the extractions
Each runnable module here was rewritten to be framework-agnostic: framework
view/model base classes were replaced with small interfaces, database access was
put behind repository/store interfaces, HTTP behind transport interfaces,
localized strings neutralized, and **all credentials, tenant identifiers, and
personal data removed**. Every module ships with a dependency-free demo.
