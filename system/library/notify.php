<?php
namespace System\Library;

use System\Library\Session;

class Notify {
    public const ERROR   = 'error';
    public const NOTICE  = 'notice';
    public const SUCCESS = 'success';
    public const WARNING = 'warning';
    public const DANGER  = 'danger';

    private const TYPE_MAP = [
        self::ERROR   => 'danger',
        self::NOTICE  => 'info',
        self::SUCCESS => 'success',
        self::WARNING => 'warning',
        self::DANGER  => 'danger',
    ];

    /**
     * Allowed types for validation.
     */
    private static array $types = [
        self::ERROR,
        self::NOTICE,
        self::SUCCESS,
        self::WARNING,
        self::DANGER,
    ];

    /**
     * HTML wrapper around the entire notification block.
     * Set to empty string to disable wrapping.
     */
    public static string $wrapper = '<div class="notifications">%s</div>';

    /**
     * Template for a single alert.
     * Placeholders: %s = Bootstrap type (e.g., 'danger'), %d = timeout, %s = message text.
     */
    public static string $alertTemplate = <<<HTML
    <div class="alert alert-%s alert-dismissible fade show" data-timeout="%d" role="alert">
        %s
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    HTML;

    /**
     * Enable/disable automatic dismissal via JavaScript.
     */
    public static bool $autoDismiss = true;

    // ----------------------------------------------------------------------
    // Internal Helper to Get Session Instance
    // ----------------------------------------------------------------------

    private static function getSession(): ?Session
    {
        // Try to get session from container if available
        if (function_exists('registry')) {
            try {
                $session = registry('session');
                return $session instanceof Session ? $session : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Fallback: use static Session::getInstance() (if you have it)
        if (class_exists(Session::class) && method_exists(Session::class, 'getInstance')) {
            return Session::getInstance();
        }

        return null;
    }

    // ----------------------------------------------------------------------
    // Main API
    // ----------------------------------------------------------------------

    /**
     * Add a notification message (or multiple messages) of a given type.
     *
     * @param string        $type     One of the class constants.
     * @param string|array  $messages Single message or array of messages.
     * @param int           $timeout  Auto-dismiss timeout in seconds (0 = never dismiss).
     */
    public static function add(string $type, string|array $messages, int $timeout = 5): void
    {
        if (!in_array($type, self::$types, true)) {
            return;
        }

        $timeout = max(0, $timeout);

        if (!is_array($messages)) {
            $messages = [$messages];
        }

        $session = self::getSession();
        if (!$session) {
            // Fallback: log or store in a static array (for CLI or tests)
            error_log("Notify: Cannot store message without session: " . implode(', ', $messages));
            return;
        }

        // Get existing flash data
        $flash = $session->get('_flash', []);
        if (!is_array($flash)) {
            $flash = [];
        }

        // Rebuild the flash array with our new messages
        foreach ($messages as $msg) {
            $flash[$type][] = [
                'text'    => $msg,
                'timeout' => $timeout,
            ];
        }

        // Store back as flash
        $session->set('_flash', $flash);
    }

    /**
     * Render all pending notifications and clear them from the session.
     *
     * @return string HTML of all alerts.
     */
    public static function read(): string
    {
        $session = self::getSession();
        if (!$session) {
            return '';
        }

        $flash = $session->get('_flash', []);
        if (empty($flash) || !is_array($flash)) {
            return '';
        }

        // Clear flash data after reading
        $session->delete('_flash');

        $html = '';

        foreach ($flash as $type => $messages) {
            $bootstrapType = self::TYPE_MAP[$type] ?? 'secondary';
            foreach ((array) $messages as $item) {
                $text = is_array($item) ? $item['text'] : $item;
                $timeout = is_array($item) && isset($item['timeout']) ? max(0, (int) $item['timeout']) : 5;

                $escapedText = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
                $html .= sprintf(
                    self::$alertTemplate,
                    $bootstrapType,
                    $timeout,
                    $escapedText
                );
            }
        }

        if (!empty(self::$wrapper)) {
            $html = sprintf(self::$wrapper, $html);
        }

        if (self::$autoDismiss) {
            $html .= self::getAutoDismissScript();
        }

        return $html;
    }

    /**
     * Remove all pending notifications without displaying them.
     */
    public static function clear(): void
    {
        $session = self::getSession();
        if ($session) {
            $session->delete('_flash');
        }
    }

    // ----------------------------------------------------------------------
    // Explicit Methods for Each Type
    // ----------------------------------------------------------------------

    public static function error(string|array $message, int $timeout = 5): void
    {
        self::add(self::ERROR, $message, $timeout);
    }

    public static function success(string|array $message, int $timeout = 5): void
    {
        self::add(self::SUCCESS, $message, $timeout);
    }

    public static function warning(string|array $message, int $timeout = 5): void
    {
        self::add(self::WARNING, $message, $timeout);
    }

    public static function notice(string|array $message, int $timeout = 5): void
    {
        self::add(self::NOTICE, $message, $timeout);
    }

    public static function danger(string|array $message, int $timeout = 5): void
    {
        self::add(self::DANGER, $message, $timeout);
    }

    // ----------------------------------------------------------------------
    // Magic Call (Convenience)
    // ----------------------------------------------------------------------

    /**
     * Allows calling `Notify::success('Message')`, `Notify::error('Oops', 3)`, etc.
     */
    public static function __callStatic(string $method, array $parameters): void
    {
        if (in_array($method, self::$types, true)) {
            $message = $parameters[0] ?? '';
            $timeout = $parameters[1] ?? 5;
            self::add($method, $message, $timeout);
        }
    }

    // ----------------------------------------------------------------------
    // Internal Helpers
    // ----------------------------------------------------------------------

    private static function getAutoDismissScript(): string
    {
        return <<<JS
        <script>
            (function() {
                document.querySelectorAll('.alert[data-timeout]').forEach(function(el) {
                    const timeout = parseInt(el.getAttribute('data-timeout'), 10);
                    if (timeout > 0) {
                        setTimeout(function() {
                            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                                const alert = bootstrap.Alert.getInstance(el) || new bootstrap.Alert(el);
                                alert.close();
                            } else {
                                el.classList.remove('show');
                                setTimeout(function() { el.remove(); }, 150);
                            }
                        }, timeout * 1000);
                    }
                });
            })();
        </script>
        JS;
    }
}