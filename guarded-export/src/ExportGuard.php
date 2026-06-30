<?php

declare(strict_types=1);

namespace Portfolio\GuardedExport;

use Countable;
use Throwable;
use Traversable;

/**
 * Runs a data export in a guarded context: prevents double-submits with a short
 * session lock, validates the dataset, optionally rejects oversized payloads
 * before streaming, and routes every failure through a single error callback.
 *
 * Framework-agnostic: fetching and exporting are supplied as callables.
 */
final class ExportGuard
{
    private const LOCK_KEY = 'export_lock_until';

    /**
     * @param callable():mixed       $fetch   Returns the dataset (array|Traversable), or false/null on failure.
     * @param callable(mixed):void   $export  Streams the dataset to the client (typically sends headers and exits).
     * @param callable(string):void  $onError Renders a user-facing error message.
     * @param array{timeout?:int,requireNonEmpty?:bool,maxBytes?:int} $options
     */
    public static function run(
        callable $fetch,
        callable $export,
        callable $onError,
        array $options = []
    ): void {
        $timeout         = (int)($options['timeout'] ?? 25);
        $requireNonEmpty = (bool)($options['requireNonEmpty'] ?? false);
        $maxBytes        = isset($options['maxBytes']) ? (int)$options['maxBytes'] : null;

        // 1. Reject repeated clicks/requests from the same session.
        $now = time();
        if (isset($_SESSION[self::LOCK_KEY]) && (int)$_SESSION[self::LOCK_KEY] > $now) {
            $onError('An export is already running. Please try again in a moment.');
            return;
        }

        // Short-lived lock (max 5s). We never rely on clearing it, because the
        // export callback usually exits — so we keep the window deliberately small.
        $lockSeconds = min(5, max(1, $timeout));
        $_SESSION[self::LOCK_KEY] = $now + $lockSeconds;

        // Release the session lock so concurrent requests don't block on it.
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        // 2. Fetch the data, converting any low-level error into a clean message.
        try {
            $data = $fetch();
        } catch (Throwable $e) {
            $onError('The export query failed or hit an unexpected error.');
            return;
        }

        // 3. Validate the result.
        if ($data === false || $data === null) {
            $onError('Export failed: no data, or the query returned an error.');
            return;
        }

        if ($requireNonEmpty && self::isEmptyResult($data)) {
            $onError('Export failed: no results found for the current selection.');
            return;
        }

        // 4. Size pre-flight: abort before streaming if the payload is too large.
        if ($maxBytes !== null && $maxBytes > 0) {
            $approx = self::estimateBytes($data);
            if ($approx !== null && $approx > $maxBytes) {
                $limit = self::formatBytes($maxBytes);
                $onError("Export too large: it exceeds about {$limit}. Please refine your selection.");
                return;
            }
        }

        // 5. Signal the front-end that a download is starting (used to hide a
        //    loading overlay once headers arrive).
        @setcookie('export_ready', '1', 0, '/');

        // Disable compression and clear buffers so the binary download is intact.
        @ini_set('zlib.output_compression', 'Off');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 6. Stream. The callback normally sends headers and exits.
        $export($data);
    }

    private static function isEmptyResult(mixed $data): bool
    {
        if (is_array($data) || $data instanceof Countable) {
            return count($data) === 0;
        }
        return false;
    }

    /**
     * Approximates the in-memory byte size of the dataset. Not exact, but a
     * reliable pre-flight guard: gzipped JSON length roughly tracks final
     * XLSX (ZIP) size. Falls back to serialize() when JSON is not possible.
     */
    private static function estimateBytes(mixed $data): ?int
    {
        if (is_string($data)) {
            return strlen($data);
        }

        if ($data instanceof Traversable) {
            $data = iterator_to_array($data);
        }

        $json = json_encode($data);
        if ($json === false) {
            return strlen(serialize($data));
        }

        if (function_exists('gzencode')) {
            $gz = @gzencode($json, 6);
            if ($gz !== false) {
                return strlen($gz);
            }
        }
        return strlen($json);
    }

    /** Formats a byte count as B / KB / MB for user-facing messages. */
    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
