<?php

/**
 * Optimized Dependency Injection Container
 * High-performance DI container with reflection caching and compiled services
 *
 * File: framework/Core/Container.php
 * Directory: /framework/Core/
 */

declare(strict_types=1);

namespace Framework\Core;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

class Container
{
    /** @var array<string, callable|string|object> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, bool> */
    private array $singletons = [];

    /** @var array<string, array> Cached reflection data */
    private array $reflectionCache = [];

    /** @var array<string, array> Cached dependency trees */
    private array $dependencyCache = [];

    /** @var array<string, string> Service aliases */
    private array $aliases = [];

    /** @var bool */
    private bool $cacheEnabled;

    /** @var string */
    private string $cacheFile;

    public function __construct(bool $enableCache = true)
    {
        $this->cacheEnabled = $enableCache;
        $this->cacheFile = __DIR__ . '/../../storage/cache/container.php';

        if ($this->cacheEnabled) {
            $this->loadCachedReflections();
        }
    }

    /**
     * Bind a singleton service with interface support
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Bind a service to the container with improved interface handling
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void
    {
        // Handle interface to implementation binding
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = $concrete;
        $this->singletons[$abstract] = $singleton;

        // Pre-compile reflection data for concrete classes
        if (is_string($concrete) && class_exists($concrete) && $this->cacheEnabled) {
            $this->cacheReflectionData($concrete);
        }
    }

    /**
     * Create an alias for a service
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Check if service is bound
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            isset($this->aliases[$abstract]) ||
            class_exists($abstract);
    }

    /**
     * Resolve a service from the container - Optimized version
     */
    public function get(string $abstract): mixed
    {
        // Check for alias
        $abstract = $this->aliases[$abstract] ?? $abstract;

        // Return cached singleton instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Use cached dependency tree if available
        if (isset($this->dependencyCache[$abstract])) {
            $instance = $this->buildFromCache($abstract);
        } else {
            $instance = $this->resolve($abstract);

            // Cache the dependency tree for future use
            if ($this->cacheEnabled && is_string($this->bindings[$abstract] ?? $abstract)) {
                $this->cacheDependencyTree($abstract, $instance);
            }
        }

        // Store singleton instances
        if ($this->singletons[$abstract] ?? false) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build instance from cached dependency tree
     */
    private function buildFromCache(string $abstract): object
    {
        $cacheData = $this->dependencyCache[$abstract];
        $className = $cacheData['class'];
        $dependencies = [];

        foreach ($cacheData['dependencies'] as $dependency) {
            if ($dependency['type'] === 'service') {
                $dependencies[] = $this->get($dependency['name']);
            } else {
                $dependencies[] = $dependency['default_value'];
            }
        }

        return new $className(...$dependencies);
    }

    /**
     * Cache dependency tree for faster resolution
     */
    private function cacheDependencyTree(string $abstract, object $instance): void
    {
        $className = get_class($instance);

        if (!isset($this->reflectionCache[$className])) {
            return; // Can't cache without reflection data
        }

        $reflectionData = $this->reflectionCache[$className];
        $dependencies = [];

        foreach ($reflectionData['constructor_params'] as $param) {
            if ($param['type'] && !$param['is_builtin']) {
                $dependencies[] = [
                    'type' => 'service',
                    'name' => $param['type'],
                ];
            } else {
                $dependencies[] = [
                    'type' => 'value',
                    'default_value' => $param['default_value'],
                ];
            }
        }

        $this->dependencyCache[$abstract] = [
            'class' => $className,
            'dependencies' => $dependencies,
        ];
    }

    /**
     * Resolve service - Original logic with caching
     */
    private function resolve(string $abstract): mixed
    {
        if (!isset($this->bindings[$abstract])) {
            // Try to auto-resolve if it's a class
            if (class_exists($abstract)) {
                return $this->build($abstract);
            }

            throw new InvalidArgumentException("Service [$abstract] not found in container");
        }

        $concrete = $this->bindings[$abstract];

        if (is_callable($concrete)) {
            return $concrete($this);
        } elseif (is_string($concrete)) {
            return $this->build($concrete);
        } else {
            return $concrete;
        }
    }

    /**
     * Build a class instance with optimized dependency injection
     */
    private function build(string $className): object
    {
        // Use cached reflection data if available
        if (isset($this->reflectionCache[$className])) {
            return $this->buildFromReflectionCache($className);
        }

        // Fallback to traditional reflection
        try {
            $reflection = new ReflectionClass($className);

            if (!$reflection->isInstantiable()) {
                throw new InvalidArgumentException("Class [$className] is not instantiable");
            }

            $constructor = $reflection->getConstructor();

            if (is_null($constructor)) {
                return new $className();
            }

            $dependencies = $this->resolveDependencies($constructor->getParameters());

            // Cache reflection data for future use
            if ($this->cacheEnabled) {
                $this->cacheReflectionData($className, $reflection);
            }

            return $reflection->newInstanceArgs($dependencies);

        } catch (ReflectionException $e) {
            throw new InvalidArgumentException("Cannot build [$className]: " . $e->getMessage());
        }
    }

    /**
     * Build from cached reflection data
     */
    private function buildFromReflectionCache(string $className): object
    {
        $cacheData = $this->reflectionCache[$className];

        if (!$cacheData['has_constructor']) {
            return new $className();
        }

        $dependencies = [];
        foreach ($cacheData['constructor_params'] as $param) {
            if ($param['type'] && !$param['is_builtin']) {
                $dependencies[] = $this->get($param['type']);
            } elseif ($param['has_default']) {
                $dependencies[] = $param['default_value'];
            } else {
                throw new InvalidArgumentException(
                    "Cannot resolve parameter [{$param['name']}] in class [$className]"
                );
            }
        }

        return new $className(...$dependencies);
    }

    /**
     * Cache reflection data for a class
     */
    private function cacheReflectionData(string $className, ?ReflectionClass $reflection = null): void
    {
        if (isset($this->reflectionCache[$className])) {
            return; // Already cached
        }

        try {
            if (!$reflection) {
                $reflection = new ReflectionClass($className);
            }

            $constructor = $reflection->getConstructor();
            $constructorParams = [];

            if ($constructor) {
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    $constructorParams[] = [
                        'name' => $param->getName(),
                        'type' => $type ? $type->getName() : null,
                        'is_builtin' => $type ? $type->isBuiltin() : true,
                        'has_default' => $param->isDefaultValueAvailable(),
                        'default_value' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                        'allows_null' => $param->allowsNull(),
                    ];
                }
            }

            $this->reflectionCache[$className] = [
                'is_instantiable' => $reflection->isInstantiable(),
                'has_constructor' => $constructor !== null,
                'constructor_params' => $constructorParams,
                'cached_at' => time(),
            ];

        } catch (ReflectionException $e) {
            // Skip caching if reflection fails
            error_log("Failed to cache reflection data for $className: " . $e->getMessage());
        }
    }

    /**
     * Resolve dependencies using traditional reflection
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (is_null($type) || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new InvalidArgumentException(
                        "Cannot resolve parameter [{$parameter->getName()}]"
                    );
                }
            } else {
                $dependencies[] = $this->get($type->getName());
            }
        }

        return $dependencies;
    }

    /**
     * Call a method with dependency injection - Optimized
     */
    public function call(object $instance, string $method, array $parameters = []): mixed
    {
        $className = get_class($instance);
        $cacheKey = $className . '::' . $method;

        // Check method cache
        if (isset($this->reflectionCache[$cacheKey])) {
            return $this->callFromCache($instance, $method, $parameters, $cacheKey);
        }

        // Fallback to reflection
        try {
            $reflection = new ReflectionClass($instance);
            $methodReflection = $reflection->getMethod($method);
            $methodParameters = $methodReflection->getParameters();

            $dependencies = $this->resolveMethodDependencies($methodParameters, $parameters);

            // Cache method reflection data
            if ($this->cacheEnabled) {
                $this->cacheMethodData($cacheKey, $methodParameters);
            }

            return $methodReflection->invokeArgs($instance, $dependencies);

        } catch (ReflectionException $e) {
            throw new InvalidArgumentException("Cannot call method [$method]: " . $e->getMessage());
        }
    }

    /**
     * Call method from cached data
     */
    private function callFromCache(object $instance, string $method, array $parameters, string $cacheKey): mixed
    {
        $cacheData = $this->reflectionCache[$cacheKey];
        $dependencies = [];

        foreach ($cacheData['parameters'] as $param) {
            $name = $param['name'];

            if (isset($parameters[$name])) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            if ($param['type'] && !$param['is_builtin']) {
                $dependencies[] = $this->get($param['type']);
            } elseif ($param['has_default']) {
                $dependencies[] = $param['default_value'];
            } else {
                throw new InvalidArgumentException(
                    "Cannot resolve parameter [$name] in method [$method]"
                );
            }
        }

        return $instance->$method(...$dependencies);
    }

    /**
     * Cache method reflection data
     */
    private function cacheMethodData(string $cacheKey, array $parameters): void
    {
        $paramData = [];
        foreach ($parameters as $param) {
            $type = $param->getType();
            $paramData[] = [
                'name' => $param->getName(),
                'type' => $type ? $type->getName() : null,
                'is_builtin' => $type ? $type->isBuiltin() : true,
                'has_default' => $param->isDefaultValueAvailable(),
                'default_value' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
            ];
        }

        $this->reflectionCache[$cacheKey] = [
            'parameters' => $paramData,
            'cached_at' => time(),
        ];
    }

    /**
     * Resolve method dependencies
     */
    private function resolveMethodDependencies(array $methodParameters, array $provided): array
    {
        $dependencies = [];

        foreach ($methodParameters as $parameter) {
            $name = $parameter->getName();

            if (isset($provided[$name])) {
                $dependencies[] = $provided[$name];
                continue;
            }

            $type = $parameter->getType();

            if (is_null($type) || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new InvalidArgumentException(
                        "Cannot resolve parameter [$name]"
                    );
                }
            } else {
                $dependencies[] = $this->get($type->getName());
            }
        }

        return $dependencies;
    }

    /**
     * Load cached reflection data from file
     */
    private function loadCachedReflections(): void
    {
        if (!file_exists($this->cacheFile)) {
            return;
        }

        try {
            $cached = require $this->cacheFile;
            if (is_array($cached) && isset($cached['reflections'])) {
                $this->reflectionCache = $cached['reflections'];
            }
        } catch (\Throwable $e) {
            // Ignore cache loading errors
            error_log("Failed to load container cache: " . $e->getMessage());
        }
    }

    /**
     * Save reflection cache to file
     */
    public function saveCache(): bool
    {
        if (!$this->cacheEnabled || empty($this->reflectionCache)) {
            return false;
        }

        try {
            $cacheDir = dirname($this->cacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            $cacheData = [
                'generated_at' => date('Y-m-d H:i:s'),
                'reflections' => $this->reflectionCache,
                'metadata' => [
                    'cached_classes' => count($this->reflectionCache),
                    'php_version' => PHP_VERSION,
                ]
            ];

            $cacheContent = "<?php\n\n/**\n * Generated Container Cache\n * Generated at: " . date('Y-m-d H:i:s') . "\n * Do not edit manually\n */\n\nreturn " . var_export($cacheData, true) . ";\n";

            $result = file_put_contents($this->cacheFile, $cacheContent, LOCK_EX) !== false;

            if ($result) {
                chmod($this->cacheFile, 0644);
            }

            return $result;

        } catch (\Throwable $e) {
            error_log("Failed to save container cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all caches
     */
    public function clearCache(): bool
    {
        $this->reflectionCache = [];
        $this->dependencyCache = [];

        if (file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }

        return true;
    }

    /**
     * Get container statistics
     */
    public function getStats(): array
    {
        return [
            'bindings' => count($this->bindings),
            'instances' => count($this->instances),
            'singletons' => count(array_filter($this->singletons)),
            'aliases' => count($this->aliases),
            'cached_reflections' => count($this->reflectionCache),
            'cached_dependencies' => count($this->dependencyCache),
            'cache_enabled' => $this->cacheEnabled,
            'cache_file_exists' => file_exists($this->cacheFile),
        ];
    }

    /**
     * Warm up the container cache by pre-loading common services
     */
    public function warmUp(array $services = []): void
    {
        foreach ($services as $service) {
            try {
                if (is_string($service) && class_exists($service)) {
                    $this->cacheReflectionData($service);
                }
            } catch (\Throwable $e) {
                // Continue warming up other services
                error_log("Failed to warm up service $service: " . $e->getMessage());
            }
        }
    }

    /**
     * Flush specific service from cache
     */
    public function flush(string $abstract): void
    {
        unset($this->instances[$abstract]);
        unset($this->dependencyCache[$abstract]);

        // Remove from reflection cache if it's a class
        if (class_exists($abstract)) {
            unset($this->reflectionCache[$abstract]);
        }
    }

    /**
     * Get cached reflection data (for debugging)
     */
    public function getReflectionCache(): array
    {
        return $this->reflectionCache;
    }
}