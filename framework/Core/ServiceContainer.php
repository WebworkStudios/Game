<?php


declare(strict_types=1);

namespace Framework\Core;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use InvalidArgumentException;
use RuntimeException;

/**
 * Dependency Injection Container mit Auto-wiring Support
 *
 * Unterstützt:
 * - Service Registration (Singleton & Transient)
 * - Auto-wiring über Constructor Injection
 * - Interface-to-Implementation Binding
 * - Lazy Loading
 * - Zirkuläre Abhängigkeiten-Erkennung
 */
class ServiceContainer
{
    private const string SINGLETON = 'singleton';
    private const string TRANSIENT = 'transient';

    private array $services = [];
    private array $instances = [];
    private array $bindings = [];
    private array $resolving = [];

    /**
     * Registriert einen Service als Singleton
     */
    public function singleton(string $abstract, string|callable|null $concrete = null): void
    {
        $this->register($abstract, $concrete, self::SINGLETON);
    }

    /**
     * Registriert einen Service als Transient (neue Instanz bei jedem Aufruf)
     */
    public function transient(string $abstract, string|callable|null $concrete = null): void
    {
        $this->register($abstract, $concrete, self::TRANSIENT);
    }

    /**
     * Bindet ein Interface an eine konkrete Implementierung
     */
    public function bind(string $interface, string $implementation): void
    {
        if (!interface_exists($interface)) {
            throw new InvalidArgumentException("Interface '{$interface}' does not exist");
        }

        if (!class_exists($implementation)) {
            throw new InvalidArgumentException("Implementation class '{$implementation}' does not exist");
        }

        $this->bindings[$interface] = $implementation;
    }

    /**
     * Registriert eine bereits erstellte Instanz
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Löst einen Service auf und gibt die Instanz zurück
     */
    public function get(string $abstract): object
    {
        // Bereits erstellte Instanz zurückgeben
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Interface-Binding auflösen
        if (isset($this->bindings[$abstract])) {
            $abstract = $this->bindings[$abstract];
        }

        // Registrierten Service auflösen
        if (isset($this->services[$abstract])) {
            return $this->resolveService($abstract);
        }

        // Auto-wiring versuchen
        if (class_exists($abstract)) {
            return $this->autowire($abstract);
        }

        throw new RuntimeException("Unable to resolve service: {$abstract}");
    }

    /**
     * Prüft, ob ein Service registriert ist
     */
    public function has(string $abstract): bool
    {
        return isset($this->services[$abstract])
            || isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    /**
     * Erstellt eine neue Instanz ohne Singleton-Caching
     */
    public function make(string $abstract, array $parameters = []): object
    {
        if (isset($this->bindings[$abstract])) {
            $abstract = $this->bindings[$abstract];
        }

        return $this->createInstance($abstract, $parameters);
    }

    /**
     * Registriert einen Service
     */
    private function register(string $abstract, string|callable|null $concrete, string $type): void
    {
        $this->services[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'type' => $type,
        ];
    }

    /**
     * Löst einen registrierten Service auf
     */
    private function resolveService(string $abstract): object
    {
        $service = $this->services[$abstract];

        // Singleton: Instanz cachen
        if ($service['type'] === self::SINGLETON && isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $instance = $this->createInstance($service['concrete']);

        // Singleton-Instanz cachen
        if ($service['type'] === self::SINGLETON) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Erstellt eine Instanz mit Auto-wiring
     */
    private function autowire(string $class): object
    {
        $instance = $this->createInstance($class);

        // Auto-wired Klassen standardmäßig als Singleton cachen
        $this->instances[$class] = $instance;

        return $instance;
    }

    /**
     * Erstellt eine neue Instanz einer Klasse
     */
    private function createInstance(string|callable $concrete, array $parameters = []): object
    {
        // Callable (Factory) ausführen
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        // Zirkuläre Abhängigkeiten prüfen
        if (isset($this->resolving[$concrete])) {
            throw new RuntimeException("Circular dependency detected: {$concrete}");
        }

        $this->resolving[$concrete] = true;

        try {
            $reflection = new ReflectionClass($concrete);

            // Klasse muss instanziierbar sein
            if (!$reflection->isInstantiable()) {
                throw new RuntimeException("Class {$concrete} is not instantiable");
            }

            $constructor = $reflection->getConstructor();

            // Keine Constructor-Parameter
            if ($constructor === null) {
                return new $concrete();
            }

            // Constructor-Parameter auflösen
            $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

            return $reflection->newInstanceArgs($dependencies);

        } catch (ReflectionException $e) {
            throw new RuntimeException("Failed to resolve class {$concrete}: " . $e->getMessage());
        } finally {
            unset($this->resolving[$concrete]);
        }
    }

    /**
     * Löst Constructor-Parameter auf
     */
    private function resolveDependencies(array $parameters, array $userParameters = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // User-Parameter haben Vorrang
            if (isset($userParameters[$name])) {
                $dependencies[] = $userParameters[$name];
                continue;
            }

            // Typ-basierte Auflösung
            $type = $parameter->getType();

            if ($type !== null && !$type->isBuiltin()) {
                $className = $type->getName();
                $dependencies[] = $this->get($className);
                continue;
            }

            // Default-Wert verwenden
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Nullable Parameter
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new RuntimeException(
                "Cannot resolve parameter '{$name}' for class " .
                $parameter->getDeclaringClass()->getName()
            );
        }

        return $dependencies;
    }
}