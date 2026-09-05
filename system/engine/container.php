<?php
namespace System\Engine;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $aliases = [];
    private array $singletons = [];
    private array $resolving = [];

    public function bind(string $abstract, callable|string $concrete = null, bool $singleton = false): self
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];

        return $this;
    }

    public function singleton(string $abstract, callable|string $concrete = null): self
    {
        return $this->bind($abstract, $concrete, true);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if ($this->has($abstract)) {
            $binding = $this->bindings[$abstract];
            
            if ($binding['singleton'] && isset($this->instances[$abstract])) {
                return $this->instances[$abstract];
            }

            $concrete = $binding['concrete'];
            
            if (is_callable($concrete)) {
                $object = $concrete($this, $parameters);
            } else {
                $object = $this->resolve($concrete, $parameters);
            }

            if ($binding['singleton']) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        return $this->resolve($abstract, $parameters);
    }

    private function resolve(string $class, array $parameters = []): mixed
    {
        if (isset($this->resolving[$class])) {
            throw new RuntimeException("Circular dependency detected for: {$class}");
        }

        $this->resolving[$class] = true;

        try {
            $reflection = new ReflectionClass($class);

            if (!$reflection->isInstantiable()) {
                throw new RuntimeException("Class {$class} is not instantiable");
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $dependencies = [];
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                
                if ($type === null || $type->isBuiltin()) {
                    if (isset($parameters[$parameter->getName()])) {
                        $dependencies[] = $parameters[$parameter->getName()];
                    } elseif ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new RuntimeException("Cannot resolve parameter: {$parameter->getName()}");
                    }
                } else {
                    $dependencyClass = $type->getName();
                    if (isset($parameters[$dependencyClass])) {
                        $dependencies[] = $parameters[$dependencyClass];
                    } else {
                        $dependencies[] = $this->make($dependencyClass);
                    }
                }
            }

            return $reflection->newInstanceArgs($dependencies);

        } catch (ReflectionException $e) {
            throw new RuntimeException("Cannot resolve {$class}: {$e->getMessage()}", 0, $e);
        } finally {
            unset($this->resolving[$class]);
        }
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    public function alias(string $alias, string $abstract): self
    {
        $this->aliases[$alias] = $abstract;
        return $this;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }
}