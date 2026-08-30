<?php
namespace System\Engine;

use RuntimeException;

class Registry {
    private array $services = [];
    private array $instances = [];
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self {
        return self::$instance ??= new self();
    }

    // --- Magic Methods ---

    public function __get(string $id): mixed {
        return $this->get($id);
    }

    public function __set(string $id, callable|object $service): void {
        $this->set($id, $service);
    }

    public function __isset(string $id): bool {
        return $this->has($id);
    }

    public function __unset(string $id): void {
        unset($this->instances[$id], $this->services[$id]);
    }

    public function set(string $id, callable|object $service): void {
        if (is_object($service) && !is_callable($service)) {
            $this->instances[$id] = $service;
        } else {
            $this->services[$id] = $service;
        }
    }

    public function get(string $id): mixed {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (array_key_exists($id, $this->services)) {
            return $this->instances[$id] = $this->services[$id]($this);
        }

        throw new RuntimeException("Service '{$id}' not found in container.");
    }

    public function has(string $id): bool {
        return array_key_exists($id, $this->instances) || array_key_exists($id, $this->services);
    }
}