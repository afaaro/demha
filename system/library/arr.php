<?php

namespace System\Library;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;
use RuntimeException;

class Arr implements IteratorAggregate
{
    protected array $stack;

    public function __construct(array $stack = [])
    {
        $this->stack = $stack;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function create(array $stack = []): static
    {
        return new static($stack);
    }

    // -------------------------------------------------------------------------
    // Original Static Methods (unchanged)
    // -------------------------------------------------------------------------

    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

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

    public static function flatten(array $array): array
    {
        return static::dot($array);
    }

    public static function undot(array $dotArray): array
    {
        $result = [];
        foreach ($dotArray as $key => $value) {
            static::set($result, $key, $value);
        }
        return $result;
    }

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

    public static function erase(array &$array, string $key): void
    {
        static::forget($array, $key);
    }

    public static function has(array $array, string $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }
        return true;
    }

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

    // Original static only() – keep it as is.
    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    public static function filter_recursive(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = static::filter_recursive($value);
            }
            if (empty($value)) {
                unset($array[$key]);
            }
        }
        return $array;
    }

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

    public static function isAssoc(array $array): bool
    {
        return !array_is_list($array);
    }

    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }

    // -------------------------------------------------------------------------
    // Instance (Fluent) Methods – no conflicts with static methods
    // -------------------------------------------------------------------------

    public function all(): array
    {
        return $this->stack;
    }

    public function first(): mixed
    {
        return reset($this->stack);
    }

    public function last(): mixed
    {
        return end($this->stack);
    }

    public function count(): int
    {
        return count($this->stack);
    }

    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->stack);
    }

    public function push(mixed $value): static
    {
        $this->stack[] = $value;
        return $this;
    }

    public function prepend(mixed $value): static
    {
        array_unshift($this->stack, $value);
        return $this;
    }

    public function map(callable $callback): static
    {
        $this->stack = array_map($callback, $this->stack);
        return $this;
    }

    public function filter(?callable $callback = null): static
    {
        $this->stack = array_filter($this->stack, $callback ?? fn($v) => !empty($v));
        return $this;
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->stack, $callback, $initial);
    }

    public function each(callable $callback): static
    {
        foreach ($this->stack as $key => $value) {
            $callback($value, $key);
        }
        return $this;
    }

    public function sort(int $flags = SORT_REGULAR): static
    {
        asort($this->stack, $flags);
        return $this;
    }

    public function sortKeys(int $flags = SORT_REGULAR): static
    {
        ksort($this->stack, $flags);
        return $this;
    }

    public function unique(int $flags = SORT_STRING): static
    {
        return new static(array_unique($this->stack, $flags));
    }

    public function shuffle(): static
    {
        $shuffled = $this->stack;
        shuffle($shuffled);
        return new static($shuffled);
    }

    /**
     * Instance `except` – implemented inline (no conflict).
     */
    public function except(array|string $keys): static
    {
        $keep = static::only($this->stack, array_diff(array_keys($this->stack), (array) $keys));
        return new static($keep);
    }

    public function merge(array $array): static
    {
        $this->stack = array_merge($this->stack, $array);
        return $this;
    }

    public function mergeRecursive(array $array): static
    {
        $this->stack = static::merge_replace($this->stack, $array);
        return $this;
    }

    public function toArray(): array
    {
        return $this->stack;
    }

    public function toJson(int $options = 0): string
    {
        $json = json_encode($this->stack, $options);
        if ($json === false) {
            throw new RuntimeException('Failed to encode array to JSON: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Magic method to handle instance calls that map to static helpers.
     * Currently handles `only()` – forwards to the static method.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'only') {
            return new static(static::only($this->stack, ...$arguments));
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $name));
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->stack);
    }
}