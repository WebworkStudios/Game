<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Http\HttpMethod;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Router Cache - Verwaltet Caching der Route-Tabelle
 */
class RouterCache
{
    private const string CACHE_HEADER = "<?php\n\n// Auto-generated route cache - DO NOT EDIT\n// Generated at: %s\n\nreturn %s;\n";

    public function __construct(
        private readonly string $cacheFile,
        private readonly string $actionsPath,
    ) {}

    /**
     * Lädt RouteEntry-Objekte (Hauptmethode für Router)
     */
    public function loadRouteEntries(): array
    {
        if ($this->isCacheValid()) {
            return $this->loadFromCache();
        }

        $routes = $this->scanRoutes();
        $this->saveToCache($routes);

        return $routes;
    }

    /**
     * Prüft ob Cache noch gültig ist
     */
    private function isCacheValid(): bool
    {
        if (!file_exists($this->cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($this->cacheFile);
        if ($cacheTime === false) {
            return false;
        }

        // Prüfe alle Action-Dateien
        foreach ($this->getActionFiles() as $file) {
            if (filemtime($file) > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lädt RouteEntry-Objekte aus Cache-Datei
     */
    private function loadFromCache(): array
    {
        $cacheData = require $this->cacheFile;

        // Konvertiere Arrays zurück zu RouteEntry-Objekten
        return array_map(function (array $data) {
            return new RouteEntry(
                pattern: $data['pattern'],
                methods: array_map(fn(string $method) => HttpMethod::from($method), $data['methods']),
                action: $data['action'],
                middlewares: $data['middlewares'],
                name: $data['name'],
                parameters: $data['parameters'],
            );
        }, $cacheData);
    }

    /**
     * Scannt Action-Verzeichnis und gibt RouteEntry-Objekte zurück
     */
    private function scanRoutes(): array
    {
        $routes = [];

        foreach ($this->getActionFiles() as $file) {
            $className = $this->getClassNameFromFile($file);
            if ($className === null) {
                continue;
            }

            $reflection = new \ReflectionClass($className);
            $attributes = $reflection->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();

                $routes[] = new RouteEntry(
                    pattern: $route->getPattern(),
                    methods: $route->getValidatedMethods(),
                    action: $className,
                    middlewares: $route->middlewares,
                    name: $route->name,
                    parameters: $route->getParameters(),
                );
            }
        }

        return $routes;
    }

    /**
     * Speichert RouteEntry-Objekte als Arrays in Cache-Datei
     */
    private function saveToCache(array $routes): void
    {
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            throw new RuntimeException("Cannot create cache directory: {$cacheDir}");
        }

        // Konvertiere RouteEntry-Objekte zu serialisierbaren Arrays
        $cacheData = array_map(function (RouteEntry $route) {
            return [
                'pattern' => $route->pattern,
                'methods' => array_map(fn(HttpMethod $method) => $method->value, $route->methods),
                'action' => $route->action,
                'middlewares' => $route->middlewares,
                'name' => $route->name,
                'parameters' => $route->parameters,
            ];
        }, $routes);

        $content = sprintf(
            self::CACHE_HEADER,
            date('Y-m-d H:i:s'),
            var_export($cacheData, true)
        );

        if (file_put_contents($this->cacheFile, $content, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write to cache file: {$this->cacheFile}");
        }

        // Opcache invalidieren falls aktiv
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cacheFile, true);
        }
    }

    /**
     * Holt alle Action-Dateien
     */
    private function getActionFiles(): array
    {
        if (!is_dir($this->actionsPath)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->actionsPath)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Extrahiert Klassennamen aus PHP-Datei
     */
    private function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        // Namespace extrahieren
        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            return null;
        }

        // Klassennamen extrahieren
        if (!preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            return null;
        }

        return $namespaceMatches[1] . '\\' . $classMatches[1];
    }

    /**
     * Löscht Cache-Datei
     */
    public function clearCache(): bool
    {
        if (file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }

        return true;
    }
}