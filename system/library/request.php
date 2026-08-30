<?php

namespace System\Library;

class Request {
    private array $get;
    private array $post;
    private array $server;
    private array $cookie;

    private array $routeParams = [];
    private array $all = [];
    protected array $matchedRoute = [];

    public function __construct()
    {
        $this->get    = $_GET;
        $this->post   = $_POST;
        $this->server = $_SERVER;
        $this->cookie = $_COOKIE;

        $this->refreshAll();
    }

    /* ---------------------------------------------------------
     * Input
     * --------------------------------------------------------- */

    public function input(?string $key = null, $filter = null, $default = null)
    {
        return $this->retrieve($this->all, $key, $filter, $default);
    }

    public function get(?string $key = null, $filter = null, $default = null)
    {
        if ($key === null) {
            return $this->retrieve($this->get, null, $filter, $default);
        }

        $value = $this->get[$key] ?? $this->routeParams[$key] ?? $default;
        return $this->applyFilter($value, $filter, $default);
    }

    public function post(?string $key = null, $filter = null, $default = null)
    {
        return $this->retrieve($this->post, $key, $filter, $default);
    }

    public function cookie(?string $key = null, $filter = null, $default = null)
    {
        return $this->retrieve($this->cookie, $key, $filter, $default);
    }

    public function server(string $key, $filter = null, $default = null)
    {
        return $this->retrieve($this->server, $key, $filter, $default);
    }

    public function all(): array
    {
        return $this->all;
    }

    public function file(string $key): ?array
    {
        if (
            !isset($_FILES[$key]) ||
            !is_array($_FILES[$key]) ||
            ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return null;
        }

        return $_FILES[$key];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all)
            || array_key_exists($key, $this->get)
            || array_key_exists($key, $this->post)
            || array_key_exists($key, $this->routeParams)
            || $this->file($key) !== null;
    }

    /* ---------------------------------------------------------
     * Route parameters
     * --------------------------------------------------------- */

    public function set(string $key, mixed $value): void
    {
        $this->routeParams[$key] = $value;
        $this->refreshAll();
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
        $this->refreshAll();
    }

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function route(?string $key = null, $filter = null, $default = null)
    {
        if ($key === null) {
            return $this->routeParams;
        }
        $value = $this->routeParams[$key] ?? $default;
        return $filter ? $this->applyFilter($value, $filter, $default) : $value;
    }

    public function setMatchedRoute(array $route): void
    {
        $this->matchedRoute = $route;
    }

    public function matchedRoute(?string $key = null, mixed $default = null): mixed
    {
        return $key === null
            ? $this->matchedRoute
            : ($this->matchedRoute[$key] ?? $default);
    }

    /* ---------------------------------------------------------
     * HTTP
     * --------------------------------------------------------- */

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }
    
    public function isAjax(): bool
    {
        return strtolower(
            (string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')
        ) === 'xmlhttprequest';
    }

    /* ---------------------------------------------------------
     * Client IP
     * --------------------------------------------------------- */

    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '');

        if (!filter_var($remote, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }

        $trusted = array_filter(
            array_map(
                'trim',
                explode(
                    ',',
                    (string) ($_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: '')
                )
            ),
            static fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP)
        );

        if (!in_array($remote, $trusted, true)) {
            return $remote;
        }

        foreach ([
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP'
        ] as $header) {
            $value = (string) ($this->server[$header] ?? '');

            foreach (explode(',', $value) as $ip) {
                $ip = trim($ip);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $remote;
    }

    /* ---------------------------------------------------------
     * Routes
     * --------------------------------------------------------- */

    public function getRoute(): string
    {
        return trim((string) $this->get('route', 'string', 'user/account'), '/');
    }

    public function isRoute(string $route): bool
    {
        $current = trim($this->getRoute(), '/');
        $route   = trim($route, '/');

        if (str_contains($route, '*')) {
            $pattern = '#^' . str_replace(
                '\*',
                '.*',
                preg_quote($route, '#')
            ) . '$#';

            return preg_match($pattern, $current) === 1;
        }

        return $current === $route;
    }

    public function segment(int $index): ?string
    {
        $parts = explode('/', trim($this->getRoute(), '/'));
        return $parts[$index] ?? null;
    }

    /* ---------------------------------------------------------
     * URL
     * --------------------------------------------------------- */

    public function getBaseUrl(): string
    {
        $host = $this->safeHost();

        return ($this->isSecure() ? 'https' : 'http')
            . '://' . $host
            . rtrim($this->getBasePath(), '/')
            . '/';
    }

    public function getBasePath(): string
    {
        return rtrim(
            dirname((string) ($this->server['SCRIPT_NAME'] ?? '')),
            '/\\'
        );
    }

    public function isSecure(): bool
    {
        return !empty($this->server['HTTPS'])
            && strtolower((string) $this->server['HTTPS']) !== 'off'
            || (int) ($this->server['SERVER_PORT'] ?? 0) === 443;
    }

    /* ---------------------------------------------------------
     * Internal
     * --------------------------------------------------------- */

    private function refreshAll(): void
    {
        $this->all = array_merge(
            $this->get,
            $this->post,
            $this->routeParams
        );
    }

    private function retrieve(
        array $source,
        ?string $key,
        mixed $filter,
        mixed $default
    ) {
        if ($key === null) {
            return $this->filterArray(
                $source,
                $filter ?? 'string'
            );
        }

        $value = $source[$key] ?? $default;
        return $this->applyFilter($value, $filter, $default);
    }

    /**
     * Apply a filter to a value.
     *
     * @param mixed $value   The value to filter (may be null, string, array, etc.)
     * @param mixed $filter  Filter definition (string, int, or array with 'filter' and 'options')
     * @param mixed $default Default value if filtering fails or value is empty/null
     * @return mixed
     */
    protected function applyFilter(mixed $value, mixed $filter, mixed $default = null)
    {
        // If value is null or an empty string, immediately return the default
        if ($value === null || $value === '') {
            return $default;
        }

        // If value is an array, recursively filter it
        if (is_array($value)) {
            return $this->filterArray($value, $filter);
        }

        // Ensure value is a string for further processing
        $value = (string) $value;

        $config = $this->normalizeFilter($filter);
        if (!$config) {
            return htmlspecialchars(
                trim($value),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        $type    = $config['type'];
        $options = $config['options'];

        return match ($type) {
            'raw' => $value,

            'string' => htmlspecialchars(
                trim($value),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ),

            'text' => trim(strip_tags($value)),

            'int', 'integer' =>
                (($v = filter_var(
                    $value,
                    FILTER_VALIDATE_INT,
                    ['options' => $options]
                )) !== false) ? $v : $default,

            'float', 'double' =>
                (($v = filter_var(
                    $value,
                    FILTER_VALIDATE_FLOAT
                )) !== false) ? $v : $default,

            'bool', 'boolean' =>
                (($v = filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                )) !== null) ? $v : $default,

            'email' =>
                (($v = filter_var(
                    $value,
                    FILTER_VALIDATE_EMAIL
                )) !== false) ? $v : $default,

            'url' =>
                (($v = filter_var(
                    $value,
                    FILTER_VALIDATE_URL
                )) !== false) ? $v : $default,

            'ip' =>
                (($v = filter_var(
                    $value,
                    FILTER_VALIDATE_IP
                )) !== false) ? $v : $default,

            'alnum' => $this->cleanAlnum($value, $default),

            'slug' => $this->cleanSlug($value, $default),

            'array' => is_array($value)
                ? (
                    !empty($options['recursive'])
                        ? $this->filterArray(
                            $value,
                            $options['recursive']
                        )
                        : $value
                )
                : $default,

            default =>
                (($v = filter_var(
                    $value,
                    $type,
                    ['options' => $options]
                )) !== false && $v !== null)
                    ? $v
                    : $default,
        };
    }

    private function filterArray(array $data, mixed $filter): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = is_array($value)
                ? $this->filterArray($value, $filter)
                : $this->applyFilter($value, $filter, null);
        }
        return $result;
    }

    private function normalizeFilter(mixed $filter): ?array
    {
        if (is_string($filter) || is_int($filter)) {
            return [
                'type' => is_string($filter)
                    ? strtolower($filter)
                    : $filter,
                'options' => []
            ];
        }

        if (!is_array($filter) || !isset($filter['filter'])) {
            return null;
        }

        $type = $filter['filter'];
        return [
            'type' => is_string($type)
                ? strtolower($type)
                : $type,
            'options' => is_array($filter['options'] ?? null)
                ? $filter['options']
                : []
        ];
    }

    private function cleanAlnum(string $value, ?string $default = null): ?string
    {
        $value = preg_replace(
            '/[^a-zA-Z0-9]/',
            '',
            $value
        );
        return $value !== '' ? $value : $default;
    }

    private function cleanSlug(string $value, ?string $default = null): ?string
    {
        $value = preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
        $value = trim($value, '-_');
        return $value !== '' ? $value : $default;
    }

    private function safeHost(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? '');

        $host = preg_replace('/:\d+$/', '', $host);

        if (
            $host === ''
            || strlen($host) > 253
            || filter_var($host, FILTER_VALIDATE_IP)
            || preg_match(
                '/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',
                $host
            ) !== 1
        ) {
            return 'localhost';
        }

        return strtolower($host);
    }
}