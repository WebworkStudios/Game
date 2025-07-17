<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Http\HttpMethod;

/**
 * Router Cache - Cached Routes für Performance
 *
 * KORRIGIERT: filemtime() Fehler behoben
 */
readonly class RouterCache
{
    public function __construct(
        private string $cacheFile,
        private string $actionsPath,
    )
    {
    }

    /**
     * Einfache get() Methode für Cache-Zugriff
     */
    public function get(): ?array
    {
        if (!$this->shouldRebuildCache()) {
            $routes = $this->loadFromCache();

            if (!empty($routes)) {
                return [
                    'routes' => $routes,
                    'namedRoutes' => $this->buildNamedRoutesArray($routes),
                ];
            }
        }

        return null;
    }

    /**
     * Einfache put() Methode für Cache-Speicherung
     */
    public function put(array $data): void
    {
        $routes = $data['routes'] ?? [];
        $this->saveToCache($routes);
    }

    /**
     * Lädt RouteEntry-Objekte (Hauptmethode für Router)
     */
    public function loadRouteEntries(): array
    {
        if (!$this->shouldRebuildCache()) {
            $cached = $this->loadFromCache();
            if (!empty($cached)) {
                return $cached;
            }
        }

        $routes = $this->buildRoutes();
        $this->saveToCache($routes);

        return $routes;
    }

    /**
     * Bestimmt ob Cache neu erstellt werden soll
     *
     * KORRIGIERT: Verwendet getRealPath() oder getPathname() für filemtime()
     */
    private function shouldRebuildCache(): bool
    {
        if (!file_exists($this->cacheFile)) {
            return true;
        }

        // Cache-Datei Timestamp
        $cacheTime = filemtime($this->cacheFile);

        // Actions Verzeichnis prüfen
        if (is_dir($this->actionsPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->actionsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                // KORRIGIERT: Verwende getPathname() statt $file direkt
                if ($file->getExtension() === 'php' && filemtime($file->getPathname()) > $cacheTime) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Lädt Routes aus Cache-Datei
     */
    private function loadFromCache(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        $cacheData = require $this->cacheFile;
        if (!is_array($cacheData)) {
            return [];
        }

        // Konvertiere Cache-Arrays zurück zu RouteEntry-Objekten
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
     * Speichert Routes in Cache-Datei
     */
    private function saveToCache(array $routes): void
    {
        // Erstelle Cache-Verzeichnis falls nötig
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Konvertiere RouteEntry-Objekte zu Arrays
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

        // Generiere Cache-Datei
        $cacheContent = "<?php\n" .
            "// Auto-generated route cache file\n" .
            "// Generated: " . date('Y-m-d H:i:s') . "\n\n" .
            "return " . var_export($cacheData, true) . ";\n";

        file_put_contents($this->cacheFile, $cacheContent, LOCK_EX);
    }

    /**
     * Erstellt Routes aus Action-Dateien
     */
    private function buildRoutes(): array
    {
        $routes = [];

        if (!is_dir($this->actionsPath)) {
            return $routes;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->actionsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());
                if ($className) {
                    $this->loadClass($className, $file->getPathname());
                    $routes = array_merge($routes, $this->extractRoutesFromClass($className));
                }
            }
        }

        return $routes;
    }

    /**
     * Extrahiert Klassen-Name aus Datei-Pfad
     */
    private function getClassNameFromFile(string $filepath): ?string
    {
        $relativePath = str_replace($this->actionsPath, '', $filepath);
        $relativePath = ltrim($relativePath, '/\\');
        $relativePath = str_replace(['/', '\\'], '\\', $relativePath);
        $relativePath = preg_replace('/\.php$/', '', $relativePath);

        // Bestimme Namespace basierend auf Pfad
        if (str_contains($filepath, '/app/Actions/')) {
            $namespace = 'App\\Actions';
            $className = str_replace(['/', '\\'], '\\', $relativePath);
            return $namespace . '\\' . $className;
        }

        return null;
    }

    /**
     * Lädt Klasse aus Datei
     */
    private function loadClass(string $className, string $filepath): void
    {
        if (!class_exists($className)) {
            require_once $filepath;
        }
    }

    /**
     * Extrahiert Routes aus Klasse via Attributes
     */
    private function extractRoutesFromClass(string $className): array
    {
        $routes = [];

        if (!class_exists($className)) {
            return $routes;
        }

        $reflection = new \ReflectionClass($className);
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Framework\\Routing\\Route') {
                $args = $attribute->getArguments();

                $route = new RouteEntry(
                    pattern: $args['path'] ?? $args[0] ?? '/',
                    methods: isset($args['methods']) ?
                        array_map(fn($m) => HttpMethod::from($m), $args['methods']) :
                        [HttpMethod::GET],
                    action: $className,
                    middlewares: $args['middlewares'] ?? [],
                    name: $args['name'] ?? null,
                    parameters: []
                );

                $routes[] = $route;
            }
        }

        return $routes;
    }

    /**
     * Erstellt Named Routes Array aus RouteEntry-Array
     */
    private function buildNamedRoutesArray(array $routes): array
    {
        $namedRoutes = [];

        foreach ($routes as $route) {
            if ($route->name !== null) {
                $namedRoutes[$route->name] = $route;
            }
        }

        return $namedRoutes;
    }

    /**
     * Löscht Cache-Datei
     */
    public function clear(): bool
    {
        if (file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }

        return true;
    }

    /**
     * Prüft ob Cache existiert
     */
    public function exists(): bool
    {
        return file_exists($this->cacheFile);
    }

    /**
     * Holt Cache-Datei-Pfad
     */
    public function getCacheFile(): string
    {
        return $this->cacheFile;
    }

    /**
     * Holt Actions-Pfad
     */
    public function getActionsPath(): string
    {
        return $this->actionsPath;
    }

    /**
     * Debug-Ausgabe der Cache-Informationen
     */
    public function debug(): array
    {
        $routes = $this->loadRouteEntries();

        return [
            'cache_file' => $this->cacheFile,
            'cache_exists' => $this->exists(),
            'cache_time' => $this->exists() ? date('Y-m-d H:i:s', filemtime($this->cacheFile)) : null,
            'actions_path' => $this->actionsPath,
            'routes_count' => count($routes),
            'named_routes_count' => count(array_filter($routes, fn($r) => $r->name !== null)),
            'routes' => array_map(fn(RouteEntry $r) => [
                'pattern' => $r->pattern,
                'methods' => array_map(fn($m) => $m->value, $r->methods),
                'action' => $r->action,
                'name' => $r->name,
            ], $routes),
        ];
    }
}