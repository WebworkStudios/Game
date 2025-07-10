<?php


declare(strict_types=1);

namespace Framework\Routing;

use Attribute;
use Framework\Http\HttpMethod;

/**
 * Route Attribute für Action-Klassen
 *
 * Definiert Routing-Informationen direkt in der Action-Klasse
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class Route
{
    /**
     * @param string $path URL-Pfad der Route (z.B. '/users/{id}')
     * @param array<string> $methods HTTP-Methoden (Standard: ['GET'])
     * @param array<class-string> $middlewares Middleware-Klassen in Ausführungsreihenfolge
     * @param string|null $name Optionaler Route-Name für URL-Generierung
     * @param array<string, string> $constraints Parameter-Constraints (z.B. ['id' => '\d+'])
     */
    public function __construct(
        public string  $path,
        public array   $methods = ['GET'],
        public array   $middlewares = [],
        public ?string $name = null,
        public array   $constraints = [],
    )
    {
    }

    /**
     * Validiert HTTP-Methoden
     */
    public function getValidatedMethods(): array
    {
        $validMethods = [];

        foreach ($this->methods as $method) {
            try {
                $validMethods[] = HttpMethod::from(strtoupper($method));
            } catch (\ValueError) {
                throw new \InvalidArgumentException("Invalid HTTP method: {$method}");
            }
        }

        return $validMethods;
    }

    /**
     * Erstellt Regex-Pattern für Pfad-Matching
     */
    public function getPattern(): string
    {
        $pattern = $this->getNormalizedPath();

        // Parameter durch Regex ersetzen
        foreach ($this->getParameters() as $param) {
            $constraint = $this->constraints[$param] ?? '[^/]+';
            $pattern = str_replace("{{$param}}", "(?P<{$param}>{$constraint})", $pattern);
        }

        return '#^' . $pattern . '$#';
    }

    /**
     * Normalisiert den Pfad
     */
    public function getNormalizedPath(): string
    {
        $path = trim($this->path, '/');
        return $path === '' ? '/' : "/{$path}";
    }

    /**
     * Extrahiert Parameter aus dem Pfad
     */
    public function getParameters(): array
    {
        preg_match_all('/\{(\w+)}/', $this->path, $matches);
        return $matches[1] ?? [];
    }
}