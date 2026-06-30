# Config-Driven CRUD (Admin / Beheer)

A back-office (admin / "Beheer") panel has dozens of near-identical "manage this
lookup list" screens — labels, products, categories, contact types, statuses,
reasons, and so on. Each is the same shape: list, add, edit, deactivate/delete.

Instead of hand-writing a controller + model per list, this engine describes
each entity **once** in a dictionary and runs every operation generically. Adding
a new managed list becomes a few lines of config, not a new code file.

## What it shows

- **Metadata-driven CRUD** — one `DictionaryCrud` engine serves any number of
  entities from a config map (table, id field, per-operation columns, defaults,
  ordering, audit messages).
- **Mass-assignment safety** — writes are whitelisted to the columns configured
  for that operation, so unexpected request fields (e.g. a smuggled
  `is_admin`) are silently dropped, never written.
- **Id protection** — the primary key can't be overwritten through update input.
- **Sensible defaults** — e.g. `status => 1` (active) applied on create.
- **Built-in audit logging** — each write emits a per-entity log message through
  an injected logger.
- **Storage-agnostic** — all data access goes through a `CrudRepository`
  interface (`ArrayCrudRepository` included for the demo; a PDO version is a thin
  wrapper).

This is the pattern behind a ~2,000-line admin module, reduced to its essence:
the value is that the *Nth* lookup screen costs almost nothing.

## Components

| Class | Responsibility |
|---|---|
| `DictionaryCrud` | Generic create/read/update/delete driven by the dictionary |
| `CrudRepository` | Data-access interface (your DB) |
| `ArrayCrudRepository` | In-memory implementation for the demo/tests |

## Usage

```php
$crud = new DictionaryCrud($dictionary, new PdoCrudRepository($pdo), $auditLogger);

$crud->overview('labels');                       // list screen
$id = $crud->create('labels', $_POST);           // only configured columns are saved
$crud->update('labels', $id, $_POST);
$crud->delete('labels', $id);
```

## Run the demo

```bash
php examples/demo.php
```

It creates rows (showing defaults applied and a bogus field dropped), lists them
sorted, updates one (showing the id can't be changed), deletes one, runs the
*same engine* against a second entity, and prints the audit log it generated.

## Original context

Extracted from the admin (`Beheer`) controller/model and its dictionary config
(`BeheerDictionaryConfig` / the `DictionaryConfig` trait). The original mapped
real domain lookups via Dutch keys and bespoke SQL column lists; this version
uses neutral English entities, moves SQL behind the `CrudRepository` interface,
and keeps the core idea: one config entry per managed list, one engine for all.
