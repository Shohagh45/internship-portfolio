<?php

declare(strict_types=1);

/**
 * Runnable demo for ExportGuard. No web server required.
 *
 *   php examples/demo.php
 *
 * The export callbacks here print instead of sending real HTTP headers/exit,
 * so we can exercise every branch in one run.
 */

require __DIR__ . '/../src/ExportGuard.php';

use Portfolio\GuardedExport\ExportGuard;

// Emulate a PHP session without sending headers (CLI context).
$_SESSION = [];

$onError = fn(string $message) => print("  [error shown to user] {$message}\n");

function scenario(string $title): void
{
    // In a real app each request is a fresh process, so the per-session lock
    // starts clear. In this single-process demo we reset it between scenarios
    // so every branch is reachable (the double-submit case re-sets it on purpose).
    unset($_SESSION['export_lock_until']);
    echo "\n== {$title} ==\n";
}

// 1. Successful export.
scenario('Successful export');
ExportGuard::run(
    fetch:  fn() => [['Alice', 30], ['Bob', 25]],
    export: function (array $rows) {
        echo "  [streaming CSV]\n";
        foreach ($rows as $row) {
            echo '  ' . implode(',', $row) . "\n";
        }
        // A real exporter would exit() here.
    },
    onError: $onError,
);

// 2. Empty result rejected because requireNonEmpty is on.
scenario('Empty result with requireNonEmpty');
ExportGuard::run(
    fetch:  fn() => [],
    export: fn($rows) => print("  [should not reach here]\n"),
    onError: $onError,
    options: ['requireNonEmpty' => true],
);

// 3. Oversized payload rejected by the size pre-flight (tiny 100-byte cap).
scenario('Oversized payload guard');
ExportGuard::run(
    fetch:  fn() => array_fill(0, 1000, ['some', 'fairly', 'wide', 'row', 'of', 'data']),
    export: fn($rows) => print("  [should not reach here]\n"),
    onError: $onError,
    options: ['maxBytes' => 100],
);

// 4. Fetch throws — surfaced as a clean message, not a stack trace.
scenario('Fetch failure is caught');
ExportGuard::run(
    fetch:  fn() => throw new RuntimeException('SQLSTATE[42S02]: table missing'),
    export: fn($rows) => print("  [should not reach here]\n"),
    onError: $onError,
);

// 5. Double-submit: pre-set the lock to simulate a request already in flight.
scenario('Double-submit is blocked');
$_SESSION['export_lock_until'] = time() + 5;
ExportGuard::run(
    fetch:  fn() => [['data']],
    export: fn($rows) => print("  [should not reach here]\n"),
    onError: $onError,
);

echo "\nDone.\n";
