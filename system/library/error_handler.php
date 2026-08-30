<?php
namespace System\Library;

use System\Library\Logger;

/**
 * Centralised error and exception handler.
 *
 * - Converts PHP errors to ErrorException
 * - Logs all errors and exceptions via Logger
 * - Shows detailed errors in debug mode
 * - Handles fatal shutdown errors
 */
class ErrorHandler
{
    private static ?Logger $logger = null;

    /**
     * Register all error handlers.
     *
     * @param Logger|null $logger  Optional logger instance
     */
    public static function register(?Logger $logger = null): void
    {
        self::$logger = $logger ?? new Logger();

        error_reporting(E_ALL);
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Convert PHP errors to ErrorException and forward to exception handler.
     */
    public static function handleError(int $code, string $message, string $file, int $line): bool
    {
        // Respect error_reporting level (including @ suppressed errors)
        if (!(error_reporting() & $code)) {
            return false;
        }

        self::handleException(new \ErrorException($message, 0, $code, $file, $line));
        return true;
    }

    /**
     * Handle uncaught exceptions.
     */
    public static function handleException(\Throwable $exception): void
    {
        // Log the exception
        self::$logger?->error($exception->getMessage(), [
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Get debug mode from config or fallback
        $debug = self::isDebugMode();

        if ($debug) {
            self::renderDebugError($exception);
        } else {
            self::renderProductionError($exception);
        }

        exit(1);
    }

    /**
     * Handle fatal shutdown errors (parse, core, compile, etc.)
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::handleException(
                new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
            );
        }
    }

    // -------------------------------------------------------------------------
    // Error Display
    // -------------------------------------------------------------------------

    private static function renderDebugError(\Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $title = 'Error: ' . get_class($exception);
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $exception->getLine();
        $trace = htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8');

        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>{$title}</title>
            <style>
                body { font-family: monospace; background: #f8f9fa; padding: 2rem; }
                .error-box { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #dc3545; margin-top: 0; }
                .details { background: #f8f9fa; padding: 1rem; border-radius: 4px; overflow: auto; }
                .label { font-weight: bold; color: #495057; }
                .trace { background: #f1f3f5; padding: 1rem; border-radius: 4px; font-size: 0.9rem; white-space: pre-wrap; word-break: break-all; max-height: 400px; overflow: auto; }
            </style>
        </head>
        <body>
            <div class="error-box">
                <h1>🔴 {$title}</h1>
                <p><span class="label">Message:</span> {$message}</p>
                <p><span class="label">File:</span> {$file} (line {$line})</p>
                <div class="details">
                    <span class="label">Stack Trace:</span>
                    <pre class="trace">{$trace}</pre>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    private static function renderProductionError(\Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        // Show a generic message (no details)
        echo <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"><title>Error</title></head>
        <body>
            <h1>Something went wrong</h1>
            <p>We're sorry, but an unexpected error occurred. Please try again later.</p>
        </body>
        </html>
        HTML;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function isDebugMode(): bool
    {
        // Check for a config service or fallback to environment variable
        if (function_exists('registry')) {
            try {
                $config = registry('config');
                if (is_object($config) && method_exists($config, 'get')) {
                    return (bool) $config->get('app.debug', false);
                }
            } catch (\Throwable $e) {
                // fall through to environment fallback
            }
        }

        $env = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false;
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set the logger instance (useful for overriding after registration).
     */
    public static function setLogger(Logger $logger): void
    {
        self::$logger = $logger;
    }
}