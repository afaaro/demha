<?php

namespace System\Library;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use RuntimeException;
use BadMethodCallException;

/**
 * Fluent array utility class providing dot-notation access, nesting,
 * merging, and transformation methods. Combines static helpers with
 * a fluent instance API for method chaining.
 *
 * All dot-notation paths use period separators: 'user.profile.name'
 *
 * @package System\Library
 */
class Arr implements IteratorAggregate
{
    /**
     * Internal array storage for the fluent instance
     *
     * @var array<array-key, mixed>
     */
    protected array $stack;

    /**
     * Create a new Arr instance
     *
     * @param array<array-key, mixed> $stack Initial array data
     */
    public function __construct(array $stack = [])
    {
        $this->stack = $stack;
    }

    /**
     * Factory method to create a new Arr instance
     *
     * @param array<array-key, mixed> $stack Initial array data
     * @return static New Arr instance
     *
     * @example Arr::create(['key' => 'value'])->getByPath('key');
     */
    public static function create(array $stack = []): static
    {
        return new static($stack);
    }

    // -------------------------------------------------------------------------
    // Static Helpers — ALL kept with original names
    // -------------------------------------------------------------------------

    /**
     * Check if a value can be accessed as an array
     *
     * @param mixed $value Value to check
     * @return bool True if array or implements ArrayAccess
     */
    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Flatten a multi-dimensional array into a flat array with dot keys
     *
     * @param array<array-key, mixed> $array Input array
     * @param string $prepend Optional prefix for all keys
     * @return array<string, mixed> Flattened array
     *
     * @example Arr::dot(['user' => ['name' => 'Ali']]);
     *          // ['user.name' => 'Ali']
     */
    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            $newKey = $prepend . $key;
            if (is_array($value) && !empty($value)) {
                $results += static::dot($value, $newKey . '.');
            } else {
                $results[$newKey] = $value;
            }
        }
        return $results;
    }

    /**
     * Alias for dot() — flatten array to dot-notation keys
     *
     * @param array<array-key, mixed> $array Input array
     * @return array<string, mixed> Flattened array
     */
    public static function flatten(array $array): array
    {
        return static::dot($array);
    }

    /**
     * Expand a dot-notation flat array back into multi-dimensional form
     *
     * @param array<string, mixed> $dotArray Array with dot keys
     * @return array<array-key, mixed> Expanded multi-dimensional array
     *
     * @example Arr::undot(['user.name' => 'Ali']);
     *          // ['user' => ['name' => 'Ali']]
     */
    public static function undot(array $dotArray): array
    {
        $result = [];
        foreach ($dotArray as $key => $value) {
            static::set($result, $key, $value);
        }
        return $result;
    }

    /**
     * Get a value from array using dot-notation path
     *
     * @param ArrayAccess|array<array-key, mixed>|null $array Data array
     * @param string $key Dot-notation path (e.g. 'db.host')
     * @param mixed $default Fallback if key not found
     * @return mixed Found value or default
     */
    public static function get($array, string $key, $default = null)
    {
        if (!static::accessible($array)) {
            return $default;
        }
        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } elseif ($array instanceof ArrayAccess && $array->offsetExists($segment)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }
        return $array;
    }

    /**
     * Set a value in array by dot-notation path (creates nested arrays)
     *
     * @param array<array-key, mixed> $array Reference to array
     * @param string $key Dot-notation path
     * @param mixed $value Value to assign
     */
    public static function set(array &$array, string $key, $value): void
    {
        if ($key === '') {
            $array = $value;
            return;
        }
        $keys = explode('.', $key);
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (!isset($array[$segment]) || !is_array($array[$segment])) {
                $array[$segment] = [];
            }
            $array = &$array[$segment];
        }
        $array[array_shift($keys)] = $value;
    }

    /**
     * Remove one or more keys from array (supports dot-notation)
     *
     * @param array<array-key, mixed> $array Reference to array
     * @param array-key|array<array-key> $keys Key or list of keys to remove
     */
    public static function forget(array &$array, $keys): void
    {
        $original = &$array;
        foreach ((array) $keys as $key) {
            $array = &$original;
            if (array_key_exists($key, $array)) {
                unset($array[$key]);
                continue;
            }
            $parts = explode('.', $key);
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (!isset($array[$part]) || !is_array($array[$part])) {
                    continue 2;
                }
                $array = &$array[$part];
            }
            unset($array[array_shift($parts)]);
        }
    }

    /**
     * Alias for forget() — remove a key by path
     *
     * @param array<array-key, mixed> $array Reference to array
     * @param string $key Dot-notation path to remove
     */
    public static function erase(array &$array, string $key): void
    {
        static::forget($array, $key);
    }

    /**
     * Check if a key exists in the array (supports dot-notation)
     *
     * @param array<array-key, mixed> $array Data array
     * @param string $key Dot-notation path
     * @return bool True if the path exists
     */
    public static function has($array, string $key): bool
    {
        if (!static::accessible($array)) {
            return false;
        }
        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } elseif ($array instanceof ArrayAccess && $array->offsetExists($segment)) {
                $array = $array[$segment];
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Recursively merge arrays — later values REPLACE earlier ones
     *
     * @param array<array-key, mixed> ...$arrays Unlimited number of arrays
     * @return array<array-key, mixed> Merged result
     */
    public static function merge_replace(...$arrays): array
    {
        $base = array_shift($arrays);
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                    $base[$key] = self::merge_replace($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            }
        }
        return $base;
    }

    /**
     * Recursively merge arrays — only add NEW keys (don't overwrite)
     *
     * @param array<array-key, mixed> ...$arrays Unlimited number of arrays
     * @return array<array-key, mixed> Merged result
     */
    public static function merge_unique(...$arrays): array
    {
        $base = array_shift($arrays);
        foreach ($arrays as $append) {
            foreach ($append as $key => $value) {
                if (!array_key_exists($key, $base)) {
                    $base[$key] = $value;
                } elseif (is_array($value) && is_array($base[$key])) {
                    $base[$key] = self::merge_unique($base[$key], $value);
                }
            }
        }
        return $base;
    }

    /**
     * Keep only the specified keys from an array
     *
     * @param array<array-key, mixed> $array Source array
     * @param array<array-key>|array-key $keys Keys to preserve
     * @return array<array-key, mixed> Array with only specified keys
     */
    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * Recursively remove empty values from array
     *
     * @param array<array-key, mixed> $array Input array
     * @param bool $preserveFalsy Keep 0, '', false, null etc.
     * @return array<array-key, mixed> Filtered array
     */
    public static function filter_recursive(array $array, bool $preserveFalsy = true): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $filtered = static::filter_recursive($value, $preserveFalsy);
                if (!empty($filtered) || $preserveFalsy) {
                    $result[$key] = $filtered;
                }
            } else {
                if ($preserveFalsy || !empty($value)) {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Extract specific columns from a multi-dimensional array
     *
     * @param array<array-key, array<array-key, mixed>> $array List of arrays
     * @param string|array<string> $value Dot-path to value to extract
     * @param string|array<string>|null $key Optional dot-path to use as key
     * @return array<array-key, mixed> Extracted values
     */
    public static function pluck(array $array, string|array $value, string|array|null $key = null): array
    {
        $valuePath = is_string($value) ? $value : implode('.', $value);
        $keyPath = is_null($key) ? null : (is_string($key) ? $key : implode('.', $key));
        $results = [];
        foreach ($array as $item) {
            $itemValue = static::get($item, $valuePath);
            if ($keyPath === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = static::get($item, $keyPath);
                $results[$itemKey] = $itemValue;
            }
        }
        return $results;
    }

    /**
     * Check if array has non-sequential (associative) keys
     *
     * @param array<array-key, mixed> $array Array to inspect
     * @return bool True if associative
     */
    public static function isAssoc(array $array): bool
    {
        return !array_is_list($array);
    }

    /**
     * Check if array is a list (0-indexed sequential keys)
     *
     * @param array<array-key, mixed> $array Array to inspect
     * @return bool True if indexed list
     */
    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }

    // -------------------------------------------------------------------------
    // Fluent Instance API — ALL renamed to avoid static conflicts ✅
    // -------------------------------------------------------------------------

    /**
     * Flatten this instance's array to dot-notation keys
     *
     * @return static New instance with flattened array
     */
    public function toDot(): static
    {
        return new static(static::dot($this->stack));
    }

    /**
     * Expand dot-notation keys back to multi-dimensional array
     *
     * @return static New instance with expanded array
     */
    public function fromDot(): static
    {
        return new static(static::undot($this->stack));
    }

    /**
     * Get value by dot-path on instance data
     * (Renamed: avoids conflict with static get())
     *
     * @param string $key Dot-notation path
     * @param mixed $default Fallback if path not found
     * @return mixed Found value or default
     */
    public function getByPath(string $key, mixed $default = null): mixed
    {
        return static::get($this->stack, $key, $default);
    }

    /**
     * Set value by dot-path on instance data
     * (Renamed: avoids conflict with static set())
     *
     * @param string $key Dot-notation path
     * @param mixed $value Value to assign
     * @return static Self instance for chaining
     */
    public function setByPath(string $key, mixed $value): static
    {
        static::set($this->stack, $key, $value);
        return $this;
    }

    /**
     * Remove key(s) by dot-path from instance data
     * (Renamed: avoids conflict with static forget())
     *
     * @param string|array<string> $keys Path(s) to remove
     * @return static Self instance for chaining
     */
    public function forgetByPath(string|array $keys): static
    {
        static::forget($this->stack, $keys);
        return $this;
    }

    /**
     * Check if dot-path exists in instance data
     * (Renamed: avoids conflict with static has())
     *
     * @param string $key Dot-notation path
     * @return bool
     */
    public function hasPath(string $key): bool
    {
        return static::has($this->stack, $key);
    }

    /**
     * Get the full underlying array
     *
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->stack;
    }

    /**
     * Get the first element
     *
     * @return mixed First value or null if empty
     */
    public function first(): mixed
    {
        return reset($this->stack) ?: null;
    }

    /**
     * Get the last element
     *
     * @return mixed Last value or null if empty
     */
    public function last(): mixed
    {
        return end($this->stack) ?: null;
    }

    /**
     * Count elements
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * Check if empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    /**
     * Check if not empty
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->stack);
    }

    /**
     * Append a value
     *
     * @param mixed $value
     * @return static Self instance for chaining
     */
    public function push(mixed $value): static
    {
        $this->stack[] = $value;
        return $this;
    }

    /**
     * Prepend a value
     *
     * @param mixed $value
     * @return static Self instance for chaining
     */
    public function prepend(mixed $value): static
    {
        array_unshift($this->stack, $value);
        return $this;
    }

    /**
     * Apply callback to every element
     *
     * @param callable(mixed,array-key):mixed $callback
     * @return static Self instance for chaining
     */
    public function map(callable $callback): static
    {
        $this->stack = array_map($callback, $this->stack);
        return $this;
    }

    /**
     * Keep only elements matching the callback
     *
     * @param callable(mixed,array-key):bool|null $callback Truth test
     * @return static Self instance for chaining
     */
    public function filter(?callable $callback = null): static
    {
        $this->stack = array_filter($this->stack, $callback ?? fn($v) => !empty($v));
        return $this;
    }

    /**
     * Reduce array to single value via callback
     *
     * @param callable(mixed,mixed,array-key):mixed $callback
     * @param mixed $initial Starting value
     * @return mixed Final accumulated value
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->stack, $callback, $initial);
    }

    /**
     * Iterate over each element (no transformation)
     *
     * @param callable(mixed,array-key):void $callback
     * @return static Self instance for chaining
     */
    public function each(callable $callback): static
    {
        foreach ($this->stack as $key => $value) {
            $callback($value, $key);
        }
        return $this;
    }

    /**
     * Sort array by value
     *
     * @param int $flags Sort behavior flags
     * @return static Self instance for chaining
     */
    public function sort(int $flags = SORT_REGULAR): static
    {
        asort($this->stack, $flags);
        return $this;
    }

    /**
     * Sort array by keys
     *
     * @param int $flags Sort behavior flags
     * @return static Self instance for chaining
     */
    public function sortKeys(int $flags = SORT_REGULAR): static
    {
        ksort($this->stack, $flags);
        return $this;
    }

    /**
     * Remove duplicate values
     *
     * @param int $flags Comparison flags
     * @return static Self instance for chaining
     */
    public function unique(int $flags = SORT_STRING): static
    {
        $this->stack = array_unique($this->stack, $flags);
        return $this;
    }

    /**
     * Randomly reorder elements
     *
     * @return static Self instance for chaining
     */
    public function shuffle(): static
    {
        shuffle($this->stack);
        return $this;
    }

    /**
     * Exclude specified keys and return new instance
     *
     * @param array<array-key>|array-key $keys Keys to remove
     * @return static New filtered instance
     */
    public function except(array|string $keys): static
    {
        $keep = static::only($this->stack, array_diff(array_keys($this->stack), (array) $keys));
        return new static($keep);
    }

    /**
     * Merge another array (reindexes numeric keys)
     *
     * @param array<array-key, mixed> $array
     * @return static Self instance for chaining
     */
    public function merge(array $array): static
    {
        $this->stack = array_merge($this->stack, $array);
        return $this;
    }

    /**
     * Recursively merge with replace semantics
     *
     * @param array<array-key, mixed> $array
     * @return static Self instance for chaining
     * @see Arr::merge_replace()
     */
    public function mergeRecursive(array $array): static
    {
        $this->stack = static::merge_replace($this->stack, $array);
        return $this;
    }

    /**
     * Get raw array copy
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->stack;
    }

    /**
     * Encode array to JSON string
     *
     * @param int $options JSON encoding flags
     * @return string
     * @throws RuntimeException On encoding failure
     */
    public function toJson(int $options = 0): string
    {
        $json = json_encode($this->stack, $options);
        if ($json === false) {
            throw new RuntimeException('Failed to encode array to JSON: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Magic read: $arr->{'user.name'}
     *
     * @param string $key Dot-notation path
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return static::get($this->stack, $key);
    }

    /**
     * Magic write: $arr->{'user.name'} = 'Ali'
     *
     * @param string $key Dot-notation path
     * @param mixed $value
     */
    public function __set(string $key, mixed $value): void
    {
        static::set($this->stack, $key, $value);
    }

    /**
     * Magic isset(): isset($arr->{'user.name'})
     *
     * @param string $key Dot-notation path
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return static::has($this->stack, $key);
    }

    /**
     * Magic unset(): unset($arr->{'user.name'})
     *
     * @param string $key Dot-notation path
     */
    public function __unset(string $key): void
    {
        static::forget($this->stack, $key);
    }

    /**
     * Route fluent method calls
     *
     * @param string $name Method name
     * @param array<mixed> $arguments Arguments
     * @return static New instance
     * @throws BadMethodCallException When method unknown
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'only') {
            return new static(static::only($this->stack, ...$arguments));
        }
        throw new BadMethodCallException(
            sprintf('Call to undefined method %s::%s()', static::class, $name)
        );
    }

    /**
     * Retrieve iterator for foreach loops
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->stack);
    }
}