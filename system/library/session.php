<?php
namespace System\Library;

use System\Library\Arr;

class Session {
    private static ?self $instance = null;
    private bool $started = false;
    private ?string $savePath = null;
    private array $options = [];

    /**
     * Private constructor – use getInstance().
     */
    private function __construct()
    {
        // Set a default writable session save path
        $this->setDefaultSavePath();
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Configuration & Session Control
    // -------------------------------------------------------------------------

    /**
     * Set a custom session save path.
     * Call this before start().
     */
    public function setSavePath(string $path): void
    {
        $this->savePath = $path;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        if (headers_sent() || session_status() !== PHP_SESSION_NONE) {
            return;
        }

        session_save_path($path);
    }

    /**
     * Determine a default, writable save path.
     */
    private function setDefaultSavePath(): void
    {
        // Use BASEDIR if defined (application root)
        if (defined('BASEDIR') && is_dir(BASEDIR)) {
            $path = BASEDIR . 'storage/sessions';
        }
        // Fallback to system temp directory
        else {
            $path = sys_get_temp_dir() . '/shaashi_sessions';
        }

        $this->setSavePath($path);
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;

        $allowed = [
            'name', 'gc_probability', 'gc_divisor', 'gc_maxlifetime',
            'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure',
            'cookie_httponly', 'cookie_samesite', 'use_cookies', 'use_only_cookies',
            'use_strict_mode', 'use_trans_sid', 'sid_length', 'sid_bits_per_character',
            'lazy_write'
        ];

        // Secure defaults (can still be overridden by explicit config options)
        $defaults = [
            'cookie_httponly' => 1,
            'use_only_cookies' => 1,
            'use_strict_mode' => 1,
            'use_trans_sid' => 0,
            'cookie_samesite' => 'Lax',
        ];

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $options)) {
                ini_set("session.$key", (string) $value);
            }
        }

        foreach ($options as $key => $value) {
            if (in_array($key, $allowed, true)) {
                ini_set("session.$key", (string)$value);
            }
        }
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            // Ensure save path is set before starting
            if ($this->savePath !== null) {
                session_save_path($this->savePath);
            }

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

            $cookiePath = (string) ($this->options['cookie_path'] ?? ini_get('session.cookie_path') ?: '/');
            $cookieDomain = (string) ($this->options['cookie_domain'] ?? ini_get('session.cookie_domain') ?: '');
            $cookieSecure = array_key_exists('cookie_secure', $this->options)
                ? (bool) $this->options['cookie_secure']
                : $isHttps;
            $cookieHttpOnly = array_key_exists('cookie_httponly', $this->options)
                ? (bool) $this->options['cookie_httponly']
                : true;
            $cookieSameSite = (string) ($this->options['cookie_samesite'] ?? ini_get('session.cookie_samesite') ?: 'Lax');

            session_set_cookie_params([
                'lifetime' => (int) ($this->options['cookie_lifetime'] ?? ini_get('session.cookie_lifetime') ?: 0),
                'path' => $cookiePath,
                'domain' => $cookieDomain,
                'secure' => $cookieSecure,
                'httponly' => $cookieHttpOnly,
                'samesite' => $cookieSameSite,
            ]);

            session_start();
        }

        $this->started = true;
    }

    public function id(): string
    {
        $this->start();
        return session_id();
    }

    public function name(): string
    {
        $this->start();
        return session_name();
    }

    public function close(): void
    {
        $this->start();
        session_write_close();
        $this->started = false;
    }

    public function regenerate(bool $destroy = false): void
    {
        $this->start();
        session_regenerate_id($destroy);
    }

    // -------------------------------------------------------------------------
    // Data Access (with dot notation)
    // -------------------------------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return Arr::get($_SESSION, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        Arr::set($_SESSION, $key, $value);
    }

    public function has(string $key): bool
    {
        $this->start();
        return Arr::has($_SESSION, $key);
    }

    public function delete(string $key): void
    {
        $this->start();
        Arr::erase($_SESSION, $key);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $this->start();
        $value = Arr::get($_SESSION, $key, $default);
        Arr::erase($_SESSION, $key);
        return $value;
    }

    public function destroy(): void
    {
        $this->start();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    'samesite' => (string) ($params['samesite'] ?? 'Lax'),
                ]
            );
        }

        session_unset();
        session_destroy();
        $this->started = false;
    }

    // -------------------------------------------------------------------------
    // Static Forwarders (backward compatible, unified via __callStatic)
    // -------------------------------------------------------------------------

    public static function __callStatic(string $name, array $arguments): mixed
    {
        $instance = self::getInstance();

        if (method_exists($instance, $name)) {
            return $instance->$name(...$arguments);
        }

        throw new \BadMethodCallException("Static method $name does not exist.");
    }
}