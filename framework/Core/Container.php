<?php

/**
 * Dependency Injection Container
 * Simple DI container for service management
 *
 * File: framework/Core/Container.php
 * Directory: /framework/Core/
 */

declare(strict_types=1);

namespace Framework\Core;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /**
     * Bind a singleton service
     *
     * @param string $abstract Service identifier
     * @param callable|string|null $concrete Service implementation
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Bind a service to the container
     *
     * @param string $abstract Service identifier
     * @param callable|string|null $concrete Service implementation
     * @param bool $singleton Whether to treat as singleton
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
        $this->singletons[$abstract] = $singleton;
    }

    /**
     * Check if service is bound
     *
     * @param string $abstract Service identifier
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Call a method with dependency injection
     *
     * @param object $instance Object instance
     * @param string $method Method name
     * @param array $parameters Additional parameters
     * @return mixed
     * @throws ReflectionException
     */
    public function call(object $instance, string $method, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass($instance);
        $method = $reflection->getMethod($method);
        $methodParameters = $method->getParameters();

        $dependencies = [];

        foreach ($methodParameters as $parameter) {
            $name = $parameter->getName();

            if (isset($parameters[$name])) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();

            if (is_null($type) || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new InvalidArgumentException(
                        "Cannot resolve parameter [$name] in method [{$method->getName()}]"
                    );
                }
            } else {
                $dependencies[] = $this->get($type->getName());
            }
        }

        return $method->invokeArgs($instance, $dependencies);
    }

    /**
     * Resolve a service from the container
     *
     * @param string $abstract Service identifier
     * @return mixed
     * @throws ReflectionException
     */
    public function get(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            // Try to auto-resolve if it's a class
            if (class_exists($abstract)) {
                return $this->build($abstract);
            }

            throw new InvalidArgumentException("Service [$abstract] not found in container");
        }

        $concrete = $this->bindings[$abstract];

        if (is_callable($concrete)) {
            $instance = $concrete($this);
        } elseif (is_string($concrete)) {
            $instance = $this->build($concrete);
        } else {
            $instance = $concrete;
        }

        if ($this->singletons[$abstract] ?? false) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build a class instance with dependency injection
     *
     * @param string $className Class name to build
     * @return object
     * @throws ReflectionException
     */
    private function build(string $className): object
    {
        $reflection = new ReflectionClass($className);

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException("Class [$className] is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if (is_null($constructor)) {
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (is_null($type) || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new InvalidArgumentException(
                        "Cannot resolve parameter [{$parameter->getName()}] in class [$className]"
                    );
                }
            } else {
                $dependencies[] = $this->get($type->getName());
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}