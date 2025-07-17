<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Http\HttpMethod;

/**
 * Router Cache - Cached Routes für Performance
 *
 * KORRIGIERT: Windows-Pfad-Unterstützung hinzugefügt
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
     * Bestimmt ob Cache neu erstellt werden soll
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
     * Einfache put() Methode für Cache-Speicherung
     */
    public function put(array $data): void
    {
        $routes = $data['routes'] ?? [];
        $this->saveToCache($routes);
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
     * Erstellt Routes aus Action-Dateien
     */
    private function buildRoutes(): array
    {
        $routes = [];

        error_log("=== BUILDING ROUTES ===");
        error_log("Actions path: " . $this->actionsPath);
        error_log("Actions path exists: " . (is_dir($this->actionsPath) ? 'YES' : 'NO'));

        if (!is_dir($this->actionsPath)) {
            error_log("Actions directory does not exist!");
            return $routes;
        }

        // Zeige alle Dateien im Actions-Verzeichnis
        $files = scandir($this->actionsPath);
        error_log("Files in actions directory: " . implode(', ', $files));

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->actionsPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $processedFiles = 0;
        foreach ($iterator as $file) {
            error_log("Processing file: " . $file->getPathname());

            if ($file->getExtension() === 'php') {
                $processedFiles++;
                error_log("  -> PHP file found: " . $file->getFilename());

                $className = $this->getClassNameFromFile($file->getPathname());
                error_log("  -> Extracted class name: " . ($className ?? 'NULL'));

                if ($className) {
                    error_log("  -> Loading class: " . $className);
                    $this->loadClass($className, $file->getPathname());

                    error_log("  -> Class exists after loading: " . (class_exists($className) ? 'YES' : 'NO'));

                    if (class_exists($className)) {
                        $classRoutes = $this->extractRoutesFromClass($className);
                        error_log("  -> Routes extracted from class: " . count($classRoutes));

                        if (!empty($classRoutes)) {
                            foreach ($classRoutes as $route) {
                                error_log("    -> Route: " . $route->pattern . " -> " . $route->action);
                            }
                        }

                        $routes = array_merge($routes, $classRoutes);
                    }
                }
            }
        }

        error_log("Total PHP files processed: " . $processedFiles);
        error_log("Total routes built: " . count($routes));

        return $routes;
    }

    /**
     * Extrahiert Klassen-Name aus Datei-Pfad
     * KORRIGIERT: Windows-Pfad-Unterstützung hinzugefügt
     */
    private function getClassNameFromFile(string $filepath): ?string
    {
        error_log("=== EXTRACTING CLASS NAME ===");
        error_log("File path: " . $filepath);

        // Normalisiere Pfad-Separatoren für Windows-Kompatibilität
        $normalizedFilePath = str_replace('\\', '/', $filepath);
        $normalizedActionsPath = str_replace('\\', '/', $this->actionsPath);

        error_log("Normalized file path: " . $normalizedFilePath);
        error_log("Normalized actions path: " . $normalizedActionsPath);

        $relativePath = str_replace($normalizedActionsPath, '', $normalizedFilePath);
        error_log("Relative path: " . $relativePath);

        $relativePath = ltrim($relativePath, '/\\');
        error_log("Trimmed path: " . $relativePath);

        $relativePath = str_replace(['/', '\\'], '\\', $relativePath);
        error_log("Normalized path: " . $relativePath);

        $relativePath = preg_replace('/\.php$/', '', $relativePath);
        error_log("Without .php: " . $relativePath);

        // Prüfe ob es ein Actions-Pfad ist (mit normalisierten Pfaden)
        if (str_contains($normalizedFilePath, '/app/Actions/')) {
            $namespace = 'App\\Actions';
            $className = $relativePath;
            $fullClassName = $namespace . '\\' . $className;
            error_log("Full class name: " . $fullClassName);
            return $fullClassName;
        }

        error_log("No valid namespace found for: " . $filepath);
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

        error_log("=== EXTRACTING ROUTES FROM CLASS ===");
        error_log("Class name: " . $className);

        if (!class_exists($className)) {
            error_log("Class does not exist: " . $className);
            return $routes;
        }

        try {
            $reflection = new \ReflectionClass($className);
            $attributes = $reflection->getAttributes();
            error_log("Attributes found: " . count($attributes));

            foreach ($attributes as $attribute) {
                error_log("Attribute name: " . $attribute->getName());

                if ($attribute->getName() === 'Framework\\Routing\\Route') {
                    error_log("Route attribute found!");

                    // KORRIGIERT: Verwende Route-Instanz statt Raw-Arguments
                    $routeInstance = $attribute->newInstance();
                    error_log("Route instance created");

                    // Verwende Route-Methoden für korrekte Pattern-Erstellung
                    $pattern = $routeInstance->getPattern();
                    $methods = $routeInstance->getValidatedMethods();
                    $parameters = $routeInstance->getParameters();

                    error_log("Route pattern: " . $pattern);
                    error_log("Route methods: " . implode(', ', array_map(fn($m) => $m->value, $methods)));
                    error_log("Route parameters: " . implode(', ', $parameters));

                    $route = new RouteEntry(
                        pattern: $pattern,
                        methods: $methods,
                        action: $className,
                        middlewares: $routeInstance->middlewares,
                        name: $routeInstance->name,
                        parameters: $parameters
                    );

                    error_log("Route created: " . $route->pattern . " -> " . $route->action);
                    $routes[] = $route;
                }
            }
        } catch (\Exception $e) {
            error_log("Error extracting routes from class " . $className . ": " . $e->getMessage());
        }

        error_log("Total routes extracted: " . count($routes));
        return $routes;
    }

    /**
     * Prüft ob Cache existiert
     */
    public function exists(): bool
    {
        return file_exists($this->cacheFile);
    }
}