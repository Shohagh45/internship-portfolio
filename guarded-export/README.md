# Guarded Export

A small, framework-agnostic wrapper that makes large data exports (Excel/CSV)
safe to trigger from a web UI. It was written to fix three real problems on a
case-management platform where users would export big reports:

1. **Double submits** — impatient users clicking "Export" repeatedly, each
   click spawning another heavy query.
2. **Runaway payloads** — a too-broad selection trying to stream hundreds of MB
   and timing out the request.
3. **Ugly failures** — a raw DB or memory error shown to the user instead of a
   readable message.

## How it works

`ExportGuard::run()` wraps the fetch-then-stream flow:

- **Short lock** — sets a per-session lock (max 5s) so repeated clicks within
  the window are rejected with a friendly message instead of re-running the
  query. The session is closed early (`session_write_close`) so concurrent
  requests don't block on the session file.
- **Validation** — treats `false`/`null` fetch results as failures, and can
  optionally reject empty result sets (`requireNonEmpty`).
- **Size pre-flight** — before streaming, it estimates the payload size
  (gzipped JSON approximation, to roughly track final XLSX/ZIP size) and aborts
  with a clear "refine your selection" message if it exceeds `maxBytes`.
- **Clean stream** — disables output compression and clears output buffers so
  the binary download isn't corrupted, signals the front-end via a cookie that
  a download is starting, then runs the export callback.
- **Graceful errors** — every failure path goes through a single `onError`
  callback so the host app decides how to render it.

The class takes **callables** for fetching and exporting, so it's decoupled
from any ORM, query builder, or templating engine.

## Usage

```php
use Portfolio\GuardedExport\ExportGuard;

ExportGuard::run(
    fetch:  fn() => $repository->bigReportRows($filters),   // returns array|Traversable|false
    export: function (array $rows) {                        // sends headers + streams, then exits
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report.csv"');
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) { fputcsv($out, $row); }
        exit;
    },
    onError: fn(string $message) => render_error_page($message),
    options: ['requireNonEmpty' => true, 'maxBytes' => 2 * 1024 * 1024],
);
```

## Run the demo

```bash
php examples/demo.php
```

The demo exercises each branch (lock, empty result, oversized payload, and a
successful export) without needing a web server.

## Original context

Extracted and generalized from `export_guard.class.php`. The original was wired
to a specific MVC view object and used localized error strings; this version
replaces the view dependency with an `onError` callable and uses neutral
English messages.
