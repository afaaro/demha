<?php

namespace System\Library;

use System\Engine\Registry;
use System\Library\Request;
use System\Library\Config;

class Url
{
    protected Registry $container;
    protected Request $request;
    protected Config $config;

    public function __construct(Registry $container)
    {
        $this->container = $container;
        $this->request = $container->get('request');
        $this->config = $container->get('config');
    }

    /**
     * Generate a URL for a route.
     */
    public function to(string $route, array $params = [], bool $absolute = false, bool $seo = false): string {
        $route = trim($route, '/');
        // Allow dots, hyphens, underscores, slashes, alphanumerics
        $route = preg_replace('/[^a-zA-Z0-9._\/-]/', '', $route);
        if ($route === '') {
            $route = $this->config->get('app.default_route', 'user/home');
        }

        $basePath = '/' . trim($this->request->getBasePath(), '/');
        $map = $this->getRouteMap($route);
        $pathSegments = [$route];

        if (!empty($map)) {
            foreach ($map as $fieldName) {
                if (array_key_exists($fieldName, $params)) {
                    // No custom sanitization – just encode
                    $pathSegments[] = rawurlencode((string) $params[$fieldName]);
                    unset($params[$fieldName]);
                }
            }
        }

        $seoEnabled = $seo || $this->config->get('app.seo.urls', false);
        $url = $seoEnabled
            ? $basePath . '/' . implode('/', $pathSegments)
            : $basePath . '/index.php?route=' . urlencode($route);

        if (!empty($params)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        return $this->finalize($url, $absolute);
    }

    /**
     * Generate a clean request URL by modifying query parameters.
     *
     * @param string|array $args     Additional query params (e.g., 'page=2' or ['page' => 2])
     * @param array        $filter   Keys to keep or remove
     * @param bool         $keep     If true, keep only $filter keys; if false, remove them
     * @param bool         $absolute Whether to return absolute URL
     * @return string
     */
    public function cleanRequest(
        string|array $args = '',
        array $filter = [],
        bool $keep = true,
        bool $absolute = false
    ): string {
        // Get current route from request
        $route = $this->request->getRoute();

        // Start with current GET parameters (excluding 'route')
        $query = $_GET;
        unset($query['route']);

        // Filter query keys
        if (!empty($filter)) {
            if ($keep) {
                $query = array_intersect_key($query, array_flip($filter));
            } else {
                $query = array_diff_key($query, array_flip($filter));
            }
        }

        // Parse additional args
        $additional = [];
        if (!empty($args)) {
            if (is_array($args)) {
                $additional = $args;
            } else {
                parse_str(ltrim($args, '&'), $additional);
            }
        }

        // Merge cleaned + additional
        $query = array_merge($query, $additional);

        // Build URL
        return $this->to($route, $query, $absolute);
    }

    /**
     * Generate URL for an asset (with cache‑busting version).
     */
    public function asset(string $path, bool $absolute = false): string
    {
        $path = ltrim($path, '/');
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Invalid asset path.');
        }

        $basePath = '/' . trim($this->request->getBasePath(), '/');

        $file = BASEDIR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        $version = is_file($file)
            ? filemtime($file)
            : time();

        $url = $basePath . '/' . $path . '?v=' . $version;

        return $this->finalize($url, $absolute);
    }

    /**
     * Get the current route from the request.
     */
    public function getCurrentRoute(): string
    {
        return $this->request->getRoute();
    }

    /**
     * Finalize URL (handle duplicate slashes and absolute).
     */
    protected function finalize(string $url, bool $absolute = false): string
    {
        $url = preg_replace('#(?<!:)/+#', '/', $url);

        if (!$absolute) {
            return $url;
        }

        $protocol = $this->request->isSecure()
            ? 'https://'
            : 'http://';

        $rawHost = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        $host = preg_replace('/[^a-zA-Z0-9\.\-:\[\]]/', '', $rawHost);
        if ($host === '' || !preg_match('/^(\[[a-fA-F0-9:]+\]|[a-zA-Z0-9.-]+)(:\d{1,5})?$/', $host)) {
            $host = 'localhost';
        }

        return $protocol . $host . $url;
    }

    /**
     * Get route map for a given route (from module's route.php).
     */
    protected function getRouteMap(string $route): array
    {
        $parts = explode('/', $route);
        $module = $parts[0] ?? '';

        $modulePath = $this->getModulePath($module);

        if ($modulePath === null) {
            return [];
        }

        $mapFile = $modulePath
            . DIRECTORY_SEPARATOR
            . 'library'
            . DIRECTORY_SEPARATOR
            . 'route.php';

        if (!is_file($mapFile)) {
            return [];
        }
        $routes = include $mapFile;

        if (!is_array($routes)) {
            return [];
        }

        $definition = $routes[$route] ?? null;

        if (is_array($definition) && isset($definition['params'])) {
            return $definition['params'];
        }

        if (is_array($definition) && array_is_list($definition)) {
            return $definition;
        }

        return [];
    }

    /**
     * Get module path – uses global function or a fallback.
     */
    protected function getModulePath(string $module): ?string
    {
        if (function_exists('get_module_path')) {
            return get_module_path($module);
        }

        // Fallback: check both modules/ and system/modules/
        $paths = [
            BASEDIR . 'modules' . DS . $module . DS,
            BASEDIR . 'system' . DS . 'modules' . DS . $module . DS,
        ];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }
        return null;
    }
}
// // Current URL: /myapp/index.php?route=user/profile&id=5&sort=asc&page=2

// // Keep only 'id' and 'page'
// $clean = $url->cleanRequest([], ['id', 'page'], true);
// // Returns: /myapp/index.php?route=user/profile&id=5&page=2

// // Remove 'sort' and add 'limit'
// $clean = $url->cleanRequest(['limit' => 10], ['sort'], false);
// // Returns: /myapp/index.php?route=user/profile&id=5&page=2&limit=10

// // Add a tab parameter
// $tabUrl = $url->cleanRequest(['section' => 'edit']);
// // Returns: /myapp/index.php?route=user/profile&id=5&sort=asc&page=2&section=edit