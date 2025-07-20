<?php
declare(strict_types=1);

namespace Framework\Core;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
/**
 * ServiceContainer - PHP 8.4 Enhanced DI Container
 */
final class ServiceContainer
{
    // PHP 8.4: Typed Constants
    private const string SINGLETON = 'singleton';
    private const string TRANSIENT = 'transient';
    private const int MAX_RECURSION_DEPTH = 10;

    private array $services = [];
    private array $instances = [];
    private array $bindings = [];
    private array $resolving = [];
    private int $recursionDepth = 0;

    /**
     * Register Singleton Service
     */
    public function singleton(string $abstract, string|callable|null $concrete = null): void
    {
        $this->register($abstract, $concrete, self::SINGLETON);
    }

    /**
     * Register Transient Service
     */
    public function transient(string $abstract, string|callable|null $concrete = null): void
    {
        $this->register($abstract, $concrete, self::TRANSIENT);
    }

    /**
     * Register Service - PHP 8.4 Optimized
     */
    private function register(string $abstract, string|callable|null $concrete, string $type): void
    {
        $this->services[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'type' => $type,
            'cached' => false
        ];
    }

    /**
     * Bind Interface to Implementation
     */
    public function bind(string $interface, string $implementation): void
    {
        match (true) {
            !interface_exists($interface) =>
            throw new \InvalidArgumentException("Interface '{$interface}' does not exist"),
            !class_exists($implementation) =>
            throw new \InvalidArgumentException("Implementation '{$implementation}' does not exist"),
            default => $this->bindings[$interface] = $implementation
        };
    }

    /**
     * Register Instance
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve Service - PHP 8.4 Enhanced
     */
    public function get(string $abstract): object
    {
        // Check recursion depth
        if ($this->recursionDepth >= self::MAX_RECURSION_DEPTH) {
            throw new \RuntimeException("Maximum recursion depth reached resolving: {$abstract}");
        }

        // Return cached instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Resolve interface binding
        if (isset($this->bindings[$abstract])) {
            $abstract = $this->bindings[$abstract];
        }

        // Resolve registered service
        if (isset($this->services[$abstract])) {
            return $this->resolveService($abstract);
        }

        // Auto-wire if class exists
        if (class_exists($abstract)) {
            return $this->autowire($abstract);
        }

        throw new \RuntimeException("Unable to resolve service: {$abstract}");
    }

    /**
     * Resolve Registered Service - Performance Optimized
     */
    private function resolveService(string $abstract): object
    {
        $service = $this->services[$abstract];

        // Return cached singleton
        if ($service['type'] === self::SINGLETON && isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $this->recursionDepth++;

        try {
            $instance = $this->createInstance($service['concrete']);

            // Cache singleton
            if ($service['type'] === self::SINGLETON) {
                $this->instances[$abstract] = $instance;
                $this->services[$abstract]['cached'] = true;
            }

            return $instance;
        } finally {
            $this->recursionDepth--;
        }
    }

    /**
     * Create Instance - PHP 8.4 Optimized
     */
    private function createInstance(string|callable $concrete): object
    {
        // Handle callable factory
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        // Check circular dependency
        if (isset($this->resolving[$concrete])) {
            throw new \RuntimeException("Circular dependency detected: {$concrete}");
        }

        $this->resolving[$concrete] = true;

        try {
            $reflection = new \ReflectionClass($concrete);

            if (!$reflection->isInstantiable()) {
                throw new \RuntimeException("Class {$concrete} is not instantiable");
            }

            $constructor = $reflection->getConstructor();

            // No constructor parameters
            if ($constructor === null) {
                return new $concrete();
            }

            // Resolve dependencies
            $dependencies = $this->resolveDependencies($constructor->getParameters());
            return $reflection->newInstanceArgs($dependencies);

        } catch (\ReflectionException $e) {
            throw new \RuntimeException("Failed to resolve class {$concrete}: " . $e->getMessage());
        } finally {
            unset($this->resolving[$concrete]);
        }
    }

    /**
     * Resolve Constructor Dependencies - PHP 8.4 Enhanced
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = match (true) {
                $this->hasTypeHint($parameter) => $this->resolveTypedDependency($parameter),
                $parameter->isDefaultValueAvailable() => $parameter->getDefaultValue(),
                $parameter->allowsNull() => null,
                default => throw new \RuntimeException(
                    "Cannot resolve parameter '{$parameter->getName()}' for class " .
                    $parameter->getDeclaringClass()->getName()
                )
            };

            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Check Type Hint - PHP 8.4 Optimized
     */
    private function hasTypeHint(\ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();
        return $type !== null && !$type->isBuiltin();
    }

    /**
     * Resolve Typed Dependency
     */
    private function resolveTypedDependency(\ReflectionParameter $parameter): object
    {
        $type = $parameter->getType();
        $className = $type->getName();
        return $this->get($className);
    }

    /**
     * Auto-wire Class
     */
    private function autowire(string $class): object
    {
        $instance = $this->createInstance($class);

        // Cache auto-wired classes as singletons
        $this->instances[$class] = $instance;

        return $instance;
    }

    /**
     * Check Service Existence
     */
    public function has(string $abstract): bool
    {
        return isset($this->services[$abstract])
            || isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    /**
     * Make New Instance (No Caching)
     */
    public function make(string $abstract, array $parameters = []): object
    {
        if (isset($this->bindings[$abstract])) {
            $abstract = $this->bindings[$abstract];
        }

        return $this->createInstance($abstract);
    }
}