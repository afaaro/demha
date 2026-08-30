<?php

namespace System\Library;

/**
 * Configuration manager – loads, saves, and manipulates PHP config files.
 *
 * Now fully object-oriented. You can have multiple instances with
 * different base paths, and all share a static cache for performance.
 *
 * Usage:
 *   $config = new Config();
 *   $dbHost = $config->get('database.host');
 *   $config->set('app.debug', true);
 *
 *   // Magic method:
 *   $debug = $config->app('debug', false);
 */

class Config {
    /**
     * Shared cache of loaded config files across all instances.
     * Keyed by "basePath|fileKey" to support different base paths.
     *
     * @var array<string, array{path: string, data: array}>
     */
    private static array $loaded = [];

    /**
     * The base directory where config files are stored.
     */
    private string $basePath;

    /**
     * @param string|null $basePath Directory containing config files.
     *                              Defaults to BASEDIR . 'system/config/'.
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? (defined('BASEDIR') ? BASEDIR . 'system/config/' : '');
        $this->basePath = $this->normalizeBasePath($this->basePath);

        if ($this->basePath !== '' && !is_dir($this->basePath)) {
            if (!mkdir($this->basePath, 0755, true) && !is_dir($this->basePath)) {
                trigger_error("Config directory does not exist and could not be created: {$this->basePath}", E_USER_WARNING);
            }
        }
    }

    /**
     * Get the base path being used.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $this->normalizeBasePath($basePath);
    }

    // -------------------------------------------------------------------------
    // Core Methods
    // -------------------------------------------------------------------------

    /**
     * Retrieve a configuration value using dot notation.
     *
     * @param string $key     e.g. 'database.host'
     * @param mixed  $default Value to return if key not found.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        [$fileKey, $subKey] = $this->resolveFilePath($key);
        $config = self::$loaded[$fileKey]['data'] ?? [];

        if ($subKey === null) {
            return $config;
        }

        foreach (explode('.', $subKey) as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }

    /**
     * Set a configuration value using dot notation.
     * The change is immediately written to the file.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     * @throws \Exception if the file cannot be written.
     */
    public function set(string $key, mixed $value): void
    {
        [$fileKey, $subKey] = $this->resolveFilePath($key);
        
        if (!isset(self::$loaded[$fileKey])) {
            self::$loaded[$fileKey] = [
                'path' => $this->basePath . $fileKey . '.php',
                'data' => []
            ];
        }

        $ref = &self::$loaded[$fileKey]['data'];

        if ($subKey === null) {
            $ref = $value;
        } else {
            $keys = explode('.', $subKey);
            foreach ($keys as $segment) {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }
            $ref = $value;
        }

        $this->save($fileKey);
    }

    /**
     * Check if a configuration key exists (including dot notation).
     */
    public function has(string $key): bool
    {
        return $this->get($key, '__not_found__') !== '__not_found__';
    }

    /**
     * Delete a configuration key using dot notation.
     * Changes are written to the file immediately.
     */
    public function delete(string $key): void
    {
        [$fileKey, $subKey] = $this->resolveFilePath($key);
        
        if (!isset(self::$loaded[$fileKey])) {
            return;
        }

        $ref = &self::$loaded[$fileKey]['data'];

        if ($subKey === null) {
            self::$loaded[$fileKey]['data'] = [];
        } else {
            $keys = explode('.', $subKey);
            $lastIndex = count($keys) - 1;
            
            foreach ($keys as $i => $segment) {
                if (!isset($ref[$segment])) {
                    return; // key not found – nothing to delete
                }
                if ($i === $lastIndex) {
                    unset($ref[$segment]);
                } else {
                    $ref = &$ref[$segment];
                }
            }
        }

        $this->save($fileKey);
    }

    /**
     * Write the loaded configuration of a file back to disk atomically.
     *
     * @param string $fileKey The cache lookup key containing basepath and file name
     * @throws \Exception if file cannot be written.
     */
    public function save(string $fileKey): void
    {
        if (!isset(self::$loaded[$fileKey])) {
            return;
        }

        $filePath = self::$loaded[$fileKey]['path'];
        $data = self::$loaded[$fileKey]['data'];

        $exported = $this->prettyExport($data);
        $phpCode = "<?php\n\nreturn $exported;\n";

        // Atomic write via temp file to avoid corruption during race conditions
        $dir = dirname($filePath);
        $tempFile = tempnam($dir, 'cfg');
        
        if ($tempFile === false || file_put_contents($tempFile, $phpCode) === false) {
            throw new \Exception("Failed to write temporary config file for $filePath");
        }

        @chmod($tempFile, 0644);

        if (!rename($tempFile, $filePath)) {
            @unlink($tempFile);
            throw new \Exception("Failed to write config to $filePath");
        }
    }

    // -------------------------------------------------------------------------
    // Magic Methods
    // -------------------------------------------------------------------------

    /**
     * Magic call – allows calling config keys as methods, e.g.:
     *   $config->database('host', 'localhost')
     *
     * @param string $method The config key (e.g. 'database')
     * @param array  $args   Optional sub‑key and default.
     * @return mixed
     */
    public function __call(string $method, array $args): mixed
    {
        $key = $method;
        $default = null;

        if (isset($args[0])) {
            $key .= '.' . $args[0];
            $default = $args[1] ?? null;
        }

        return $this->get($key, $default);
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the file key and sub‑key from a dot‑notation string.
     *
     * @param string $key e.g. 'database.host'
     * @return array{string, string|null} [fileKey, subKey]
     */
    private function resolveFilePath(string $key): array
    {
        $parts = explode('.', $key, 2);
        $fileKey = $parts[0];
        $subKey = $parts[1] ?? null;

        // Build a cache key that includes the base path
        $cacheKey = $this->basePath . '|' . $fileKey;

        if (!isset(self::$loaded[$cacheKey])) {
            $filePath = $this->basePath . $fileKey . '.php';

            if (file_exists($filePath)) {
                $data = include $filePath;
                if (!is_array($data)) {
                    throw new \Exception("Config file $fileKey does not return an array.");
                }
                self::$loaded[$cacheKey] = [
                    'path' => $filePath,
                    'data' => $data,
                ];
            } else {
                // File doesn't exist – store empty data but remember the intended path
                self::$loaded[$cacheKey] = [
                    'path' => $filePath,
                    'data' => [],
                ];
            }
        }

        return [$cacheKey, $subKey];
    }

    private function normalizeBasePath(string $basePath): string
    {
        $basePath = trim($basePath);

        if ($basePath === '') {
            return '';
        }

        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Recursively export an array as a prettified PHP string safely.
     */
    private function prettyExport(mixed $expression, string $indent = '    ', int $depth = 1): string
    {
        if (is_array($expression)) {
            if (empty($expression)) {
                return '[]';
            }

            // Safe check for sequentially indexed arrays
            $isIndexed = array_is_list($expression);
            $lines = [];

            foreach ($expression as $key => $value) {
                $formattedKey = $isIndexed ? '' : var_export($key, true) . ' => ';
                $formattedValue = $this->prettyExport($value, $indent, $depth + 1);
                $lines[] = str_repeat($indent, $depth) . $formattedKey . $formattedValue;
            }

            return "[\n" . implode(",\n", $lines) . "\n" . str_repeat($indent, $depth - 1) . "]";
        }

        return var_export($expression, true);
    }
}

// // Get a value (with default)
// $host = $config->get('database.host', 'localhost');

// // Set a value (writes to file immediately)
// $config->set('app.debug', true);


// // Delete a key
// $config->delete('cache.driver');