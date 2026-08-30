<?php
namespace App\System\Library;

/**
 * File‑based cache with automatic invalidation based on watched directories.
 *
 * Features:
 * - Stores cache entries as serialized PHP data.
 * - Invalidates cache when any file in watched directories changes.
 * - Supports TTL (time‑to‑live) per entry.
 * - Fully object‑oriented, injectable, and testable.
 *
 * Usage:
 *   $cache = new Cache(
 *       cacheDir: '/path/to/cache',
 *       watchDirs: ['/path/to/modules']
 *   );
 *   $cache->set('myKey', $value, 3600); // expires in 1 hour
 *   $data = $cache->get('myKey');
 */
class Cache
{
    private string $cacheDir;
    private array $watchDirs;
    private static array $dirHashCache = []; // cache per instance (optional)

    /**
     * @param string|null $cacheDir  Directory to store cache files.
     *                                If null, defaults to BASEDIR . 'storage/cache/'.
     * @param array|null  $watchDirs Directories to monitor for changes.
     *                                If null, defaults to ['system/modules', 'modules'] under BASEDIR.
     * @throws \RuntimeException if cache directory cannot be created.
     */
    public function __construct(?string $cacheDir = null, ?array $watchDirs = null)
    {
        // Resolve cache directory
        if ($cacheDir === null) {
            $cacheDir = defined('BASEDIR') ? BASEDIR . 'storage/cache/' : __DIR__ . '/../storage/cache/';
        }
        $this->cacheDir = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR;

        // Resolve watch directories
        if ($watchDirs === null) {
            $base = defined('BASEDIR') ? BASEDIR : '';
            $watchDirs = [
                $base . 'system/modules',
                $base . 'modules',
            ];
        }
        $this->watchDirs = array_map(function ($dir) {
            return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        }, $watchDirs);

        // Ensure cache directory exists
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0755, true)) {
            throw new \RuntimeException("Unable to create cache directory: {$this->cacheDir}");
        }
    }

    // -------------------------------------------------------------------------
    // Core Methods
    // -------------------------------------------------------------------------

    /**
     * Retrieve a cached value by key.
     * Returns null if the key does not exist, is expired, or invalidated.
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key): mixed
    {
        $cacheFile = $this->getCacheFilePath($key);

        if (!file_exists($cacheFile)) {
            return null;
        }

        $data = @file_get_contents($cacheFile);
        if ($data === false) {
            return null;
        }

        $payload = @unserialize($data, ['allowed_classes' => false]);
        if (!is_array($payload) || !isset($payload['value'])) {
            return null;
        }

        // Check TTL
        if (isset($payload['expires']) && time() > $payload['expires']) {
            $this->delete($key);
            return null;
        }

        // Check directory hash for invalidation
        $expectedHash = $payload['_dirhash'] ?? '';
        $actualHash = $this->getDirectoryHash();
        if ($expectedHash !== $actualHash) {
            $this->delete($key);
            return null;
        }

        return $payload['value'];
    }

    /**
     * Store a value in the cache.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl   Time‑to‑live in seconds (0 = no expiration).
     * @return bool True on success, false on failure.
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $cacheFile = $this->getCacheFilePath($key);

        $data = [
            '_dirhash' => $this->getDirectoryHash(),
            'value'    => $value,
        ];

        if ($ttl > 0) {
            $data['expires'] = time() + $ttl;
        }

        $serialized = serialize($data);
        return file_put_contents($cacheFile, $serialized, LOCK_EX) !== false;
    }

    /**
     * Delete a cache entry by key.
     *
     * @param string $key
     * @return bool True if the entry was deleted, false if it didn't exist.
     */
    public function delete(string $key): bool
    {
        $cacheFile = $this->getCacheFilePath($key);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return false;
    }

    /**
     * Check if a cache key exists and is valid (not expired/invalidated).
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Clear all cache files.
     *
     * @return int Number of files deleted.
     */
    public function clear(): int
    {
        $count = 0;
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * Set the directories to watch for changes.
     *
     * @param array $dirs
     * @return self
     */
    public function setWatchDirs(array $dirs): self
    {
        $this->watchDirs = array_map(function ($dir) {
            return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        }, $dirs);
        return $this;
    }

    /**
     * Get the current watch directories.
     */
    public function getWatchDirs(): array
    {
        return $this->watchDirs;
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Get the full file path for a cache key.
     */
    private function getCacheFilePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.cache';
    }

    /**
     * Compute a hash of all files in the watched directories.
     * Used to detect file changes and invalidate the cache.
     */
    private function getDirectoryHash(): string
    {
        // Use a static per-instance cache to avoid recomputing on every get
        // (but we can't keep it too long – we compute fresh each time for accuracy)
        // We'll compute anew each call to ensure freshness.

        $hashes = [];

        foreach ($this->watchDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $hashes[] = $file->getPathname() . ':' . $file->getMTime();
                }
            }
        }

        sort($hashes);
        return md5(implode('|', $hashes));
    }
}