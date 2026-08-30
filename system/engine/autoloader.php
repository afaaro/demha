<?php

namespace System\Engine;

class Autoloader {
    /**
     * Registered namespaces
     *
     * @var array<string,array>
     */
    private array $paths = [];


    /**
     * Class aliases
     *
     * @var array<string,string>
     */
    private array $aliases = [];


    /**
     * Constructor
     */
    public function __construct()
    {
        spl_autoload_extensions('.php');

        spl_autoload_register(
            [$this, 'load']
        );
    }


    /**
     * Register namespace
     */
    public function register(
        string $namespace,
        string $directory,
        bool $psr4 = false
    ): void {

        $namespace = trim($namespace, '\\') . '\\';


        $directory = rtrim(
            $directory,
            '/\\'
        ) . DS;


        if (!isset($this->paths[$namespace])) {

            $this->paths[$namespace] = [
                'directories' => [],
                'psr4' => $psr4
            ];
        }


        if (!in_array(
            $directory,
            $this->paths[$namespace]['directories'],
            true
        )) {

            $this->paths[$namespace]['directories'][] = $directory;
        }


        /**
         * Longest namespace first
         */
        uksort(
            $this->paths,
            fn($a, $b) =>
            strlen($b) <=> strlen($a)
        );
    }


    /**
     * Register class alias
     *
     * Example:
     *
     * $loader->alias(
     *     'Controller',
     *     'App\\System\\Engine\\Controller'
     * );
     */
    public function alias(
        string $alias,
        string $class
    ): void {

        $this->aliases[strtolower($alias)] = $class;
    }


    /**
     * Register multiple aliases
     *
     * [
     *   'Controller' => 'App\\System\\Engine\\Controller'
     * ]
     */
    public function aliases(
        array $aliases
    ): void {

        foreach ($aliases as $alias => $class) {

            $this->alias(
                $alias,
                $class
            );
        }
    }


    /**
     * Load class
     */
    public function load(
        string $class
    ): bool {
        return $this->resolve($class, []);
    }

    /**
     * Resolve a class name while detecting alias loops.
     */
    private function resolve(
        string $class,
        array $stack = []
    ): bool {
        /**
         * Alias lookup
         */
        $lower = strtolower($class);

        if (isset($this->aliases[$lower])) {
            if (in_array($lower, $stack, true)) {
                throw new \RuntimeException(
                    sprintf(
                        'Circular alias detected while resolving "%s".',
                        $class
                    )
                );
            }

            $target = $this->aliases[$lower];
            $stack[] = $lower;

            if (!$this->resolve($target, $stack)) {
                return false;
            }

            if (
                class_exists(
                    $target,
                    false
                )
            ) {
                return class_alias(
                    $target,
                    $class
                );
            }

            return false;
        }

        foreach ($this->paths as $namespace => $config) {
            if (!str_starts_with(
                $class,
                $namespace
            )) {
                continue;
            }

            $relative = substr(
                $class,
                strlen($namespace)
            );

            foreach (
                $config['directories']
                as $directory
            ) {
                if ($config['psr4']) {
                    $file =
                        $directory .
                        str_replace(
                            '\\',
                            DS,
                            $relative
                        ) .
                        '.php';
                } else {
                    $file =
                        $directory .
                        str_replace(
                            '\\',
                            DS,
                            $this->camelToSnake($relative)
                        ) .
                        '.php';
                }

                if (is_file($file)) {
                    require_once $file;

                    return
                        class_exists($class, false)
                        ||
                        interface_exists($class, false)
                        ||
                        trait_exists($class, false);
                }
            }
        }

        return false;
    }


    /**
     * Convert CamelCase to snake_case
     */
    private function camelToSnake(
        string $value
    ): string {

        $value = preg_replace(
            '/([a-z0-9])([A-Z])/',
            '$1_$2',
            $value
        );


        $value = preg_replace(
            '/([A-Z]+)([A-Z][a-z])/',
            '$1_$2',
            $value
        );


        return strtolower($value);
    }


    /**
     * Get namespaces
     */
    public function getPaths(): array
    {
        return $this->paths;
    }


    /**
     * Get aliases
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }
}
