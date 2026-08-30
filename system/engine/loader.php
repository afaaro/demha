<?php

namespace System\Engine;

class Loader {
    protected \System\Engine\Registry $registry;
    private string $allowedSegmentsPattern = "/^[a-zA-Z0-9_-]+$/";

    public function __construct() {
        $this->registry = Registry::getInstance();
    }

    /**
     * Load and instantiate a library/service.
     *
     * @throws \RuntimeException If file or class does not exist.
     * @return object Instantiated library object
     */
    public function library(string $route): object
    {
        $parts = explode('/', $route);
        $this->validateRouteParts($parts);

        $module = array_shift($parts);
        $file = get_module_path($module) . DS . 'services' . DS . implode(DS, $parts) . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Library file not found: $file");
        }

        require_once $file;

        $className = $this->buildClassName($module, $parts);
        if (!class_exists($className)) {
            throw new \RuntimeException("Class $className not found in $file");
        }

        // Instantiate the class (with optional dependency injection)
        return new $className($this->registry);
    }

    /**
     * Load and instantiate a model.
     *
     * @throws \InvalidArgumentException If route contains invalid characters.
     * @throws \RuntimeException If file or class does not exist.
     */
    public function model(string $route): object
    {
        $parts = explode('/', $route);
        $this->validateRouteParts($parts);

        $module = array_shift($parts);
        $file = get_module_path($module) . DS . 'models' . DS . implode(DS, $parts) . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Model file not found: $file");
        }

        require_once $file;

        $className = $this->buildClassName($module, $parts) . 'Model';
        if (!class_exists($className)) {
            throw new \RuntimeException("Class $className not found in $file");
        }

        return new $className($this->registry);
    }

    private function validateRouteParts(array $parts): void
    {
        foreach ($parts as $part) {
            if (!preg_match($this->allowedSegmentsPattern, $part)) {
                throw new \InvalidArgumentException("Invalid route segment: $part");
            }
        }
    }

    private function buildClassName(string $module, array $parts): string
    {
        $toPascalCase = static function (string $value): string {
            $value = str_replace(['-', '_'], ' ', $value);
            return str_replace(' ', '', ucwords($value));
        };

        $name = $toPascalCase($module);
        foreach ($parts as $part) {
            $name .= $toPascalCase($part);
        }
        return $name;
    }
}