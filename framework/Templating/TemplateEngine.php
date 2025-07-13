<?php

declare(strict_types=1);

namespace Framework\Templating;

use RuntimeException;

/**
 * Template Engine - Twig-ähnliche Syntax mit Variables, Controls, Inheritance, Filtern, Caching und Fragment-Support
 */
class TemplateEngine
{
    private array $paths = [];
    private array $data = [];
    private array $blocks = [];
    private ?string $parentTemplate = null;
    private FilterManager $filterManager;
    private TemplateCache $cache;
    private array $loadedTemplates = [];

    // *** NEUE PROPERTY für Auto-Escape ***
    private bool $autoEscape = true;

    public function __construct(
        array          $templatePaths = [],
        ?TemplateCache $cache = null,
        bool           $autoEscape = true // *** Default: XSS-Schutz AN ***
    )
    {
        $this->paths = $templatePaths;
        $this->filterManager = new FilterManager();
        $this->cache = $cache ?? new TemplateCache(sys_get_temp_dir() . '/template_cache', false);
        $this->autoEscape = $autoEscape;
    }

    /**
     * Render widget/component with caching
     */
    public function renderWidget(string $template, array $data = [], int $ttl = 300, array $tags = []): string
    {
        return $this->renderCached($template, $data, $ttl, $tags);
    }

    /**
     * Render template with optional caching
     */
    public function renderCached(string $template, array $data = [], int $ttl = 0, array $tags = []): string
    {
        if ($ttl <= 0) {
            return $this->render($template, $data);
        }

        // Create cache key from template and data
        $cacheKey = 'template_' . md5($template . serialize($data));

        // Try to get from fragment cache
        if ($cached = $this->cache->getFragment($cacheKey)) {
            return $cached;
        }

        // Render and cache
        $content = $this->render($template, $data);
        $this->cache->storeFragment($cacheKey, $content, $ttl, $tags);

        return $content;
    }

    /**
     * Rendert Template mit Caching-Support
     */
    public function render(string $template, array $data = []): string
    {
        error_log("=== TemplateEngine::render START ===");
        error_log("Template: $template");

        $this->data = $data;
        $this->blocks = [];
        $this->parentTemplate = null;
        $this->loadedTemplates = []; // Reset dependency tracking

        try {
            $templatePath = $this->findTemplate($template);
            $this->loadedTemplates[] = $templatePath;

            // Try to load from cache first
            if ($this->cache->isValid($template, $this->loadedTemplates)) {
                error_log("Loading from cache: $template");
                $compiled = $this->cache->load($template);

                if ($compiled !== null) {
                    $result = $this->renderCompiled($compiled);
                    error_log("Cache hit - result length: " . strlen($result));
                    error_log("=== TemplateEngine::render END (CACHED) ===");
                    return $result;
                }
            }

            // Cache miss - parse and cache
            error_log("Cache miss - parsing template: $template");
            $content = file_get_contents($templatePath);

            error_log("Template content length: " . strlen($content));

            if (strlen($content) === 0) {
                throw new RuntimeException("Template file is empty: $templatePath");
            }

            // Parse Template
            $parsed = $this->parseTemplate($content);
            error_log("Parsed " . count($parsed) . " tokens");

            // FIRST PASS: Extract extends and blocks
            $this->extractExtendsAndBlocks($parsed);

            error_log("Parent template: " . ($this->parentTemplate ?? 'none'));
            error_log("Blocks found: " . implode(', ', array_keys($this->blocks)));

            // Prepare compiled structure
            $compiled = [
                'tokens' => $parsed,
                'blocks' => $this->blocks,
                'parent_template' => $this->parentTemplate,
                'template_path' => $templatePath,
                'dependencies' => $this->loadedTemplates,
            ];

            // Store in cache
            $this->cache->store($template, $templatePath, $compiled, $this->loadedTemplates);
            error_log("Template cached: $template");

            // Render
            $result = $this->renderCompiled($compiled);

            error_log("Final result length: " . strlen($result));
            error_log("=== TemplateEngine::render END ===");

            return $result;

        } catch (\Throwable $e) {
            error_log("TemplateEngine ERROR: " . $e->getMessage());
            error_log("Error file: " . $e->getFile() . ":" . $e->getLine());
            throw $e;
        }
    }

    /**
     * Findet Template-Datei in den konfigurierten Pfaden
     */
    private function findTemplate(string $template): string
    {
        // Add .html extension if not present
        if (!str_contains($template, '.')) {
            $template .= '.html';
        }

        foreach ($this->paths as $path) {
            // Normalize path separators for Windows
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            $normalizedTemplate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template);

            $fullPath = rtrim($normalizedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalizedTemplate, DIRECTORY_SEPARATOR);

            if (file_exists($fullPath)) {
                error_log("Template found: $fullPath");
                return $fullPath;
            }
        }

        throw new RuntimeException("Template not found: {$template} in paths: " . implode(', ', $this->paths));
    }

    /**
     * Render from compiled structure
     */
    private function renderCompiled(array $compiled): string
    {
        // Restore state from compiled structure
        $this->blocks = $compiled['blocks'] ?? [];
        $this->parentTemplate = $compiled['parent_template'] ?? null;

        $tokens = $compiled['tokens'] ?? [];

        // Handle inheritance
        if ($this->parentTemplate) {
            error_log("Using inheritance");
            return $this->renderWithInheritance();
        } else {
            error_log("Rendering without inheritance");
            return $this->renderParsed($tokens);
        }
    }

    /**
     * Render with inheritance - load parent and inject blocks
     */
    private function renderWithInheritance(): string
    {
        // Load parent template
        $parentPath = $this->findTemplate($this->parentTemplate);

        // Track dependency
        if (!in_array($parentPath, $this->loadedTemplates)) {
            $this->loadedTemplates[] = $parentPath;
        }

        $parentContent = file_get_contents($parentPath);
        $parentTokens = $this->parseTemplate($parentContent);

        // Render parent with child blocks
        return $this->renderParsed($parentTokens);
    }

    /**
     * Parst Template-Content in AST-ähnliche Struktur mit Filter-Support
     */
    private function parseTemplate(string $content): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            // Find next template tag
            $varStart = strpos($content, '{{', $offset);
            $controlStart = strpos($content, '{%', $offset);

            // Determine which comes first
            $nextTag = false;
            $nextPos = $length;

            if ($varStart !== false && ($controlStart === false || $varStart < $controlStart)) {
                $nextTag = 'variable';
                $nextPos = $varStart;
            } elseif ($controlStart !== false) {
                $nextTag = 'control';
                $nextPos = $controlStart;
            }

            // Add text before tag
            if ($nextPos > $offset) {
                $text = substr($content, $offset, $nextPos - $offset);
                if ($text !== '') {
                    $tokens[] = ['type' => 'text', 'content' => $text];
                }
            }

            if ($nextTag === false) {
                break;
            }

            // Parse the tag
            if ($nextTag === 'variable') {
                $endPos = strpos($content, '}}', $nextPos);
                if ($endPos === false) {
                    throw new RuntimeException('Unclosed variable tag');
                }

                $variable = trim(substr($content, $nextPos + 2, $endPos - $nextPos - 2));
                $tokens[] = $this->parseVariableWithFilters($variable);
                $offset = $endPos + 2;

            } elseif ($nextTag === 'control') {
                $endPos = strpos($content, '%}', $nextPos);
                if ($endPos === false) {
                    throw new RuntimeException('Unclosed control tag');
                }

                $control = trim(substr($content, $nextPos + 2, $endPos - $nextPos - 2));
                $tokens[] = $this->parseControlTag($control);
                $offset = $endPos + 2;
            }
        }

        return $tokens;
    }

    /**
     * Parst Variable mit Filtern
     */
    private function parseVariableWithFilters(string $expression): array
    {
        // Debug what we're parsing
        error_log("Parsing variable expression: '$expression'");

        // Prüfe auf Filter-Syntax: variable|filter:param1:param2
        if (!str_contains($expression, '|')) {
            return ['type' => 'variable', 'name' => $expression];
        }

        $parts = explode('|', $expression, 2);
        $variableName = trim($parts[0]);
        $filterChain = trim($parts[1]);

        // Debug
        error_log("Variable name: '$variableName', Filter chain: '$filterChain'");

        // Parse Filter-Chain
        $filters = $this->parseFilterChain($filterChain);

        return [
            'type' => 'variable',
            'name' => $variableName,
            'filters' => $filters
        ];
    }

    /**
     * Parst Filter-Chain mit erweiterten Parameter-Support
     */
    private function parseFilterChain(string $filterChain): array
    {
        $filters = [];
        $filterParts = explode('|', $filterChain);

        foreach ($filterParts as $filterExpr) {
            $filterExpr = trim($filterExpr);

            if (str_contains($filterExpr, ':')) {
                $parts = explode(':', $filterExpr, 2);
                $filterName = trim($parts[0]);
                $paramString = trim($parts[1]);

                $parameters = $this->parseFilterParameters($paramString);
            } else {
                $filterName = $filterExpr;
                $parameters = [];
            }

            $filters[] = [
                'name' => $filterName,
                'parameters' => $parameters
            ];
        }

        return $filters;
    }

    /**
     * Enhanced parameter parsing for object syntax support
     */
    private function parseFilterParameters(string $paramString): array
    {
        // Handle object syntax: {key: 'value', key2: 'value2'}
        if (str_starts_with($paramString, '{') && str_ends_with($paramString, '}')) {
            $parsed = $this->parseObjectParameters($paramString);
            return [$parsed]; // Wrap in array for filter parameter structure
        }

        // Handle simple colon-separated parameters
        $parameters = explode(':', $paramString);
        return array_map(function ($param) {
            return trim($param, '\'"');
        }, $parameters);
    }

    /**
     * Parse object-style parameters: {player: 'Messi', minute: 90}
     */
    private function parseObjectParameters(string $objectString): array
    {
        $parameters = [];
        $content = trim($objectString, '{}');

        if (empty($content)) {
            return [];
        }

        // Debug
        error_log("Parsing object parameters: '$objectString'");

        // Simple parsing for key:value pairs
        $pairs = explode(',', $content);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (str_contains($pair, ':')) {
                [$key, $value] = explode(':', $pair, 2);
                $key = trim($key, '\'" ');
                $value = trim($value, '\'" ');

                // Convert numeric values
                if (is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float)$value : (int)$value;
                }

                $parameters[$key] = $value;
            }
        }

        error_log("Parsed object parameters: " . json_encode($parameters));

        // Return array directly, not wrapped in array
        return $parameters;
    }

    /**
     * Parst Control-Tags (if, for, extends, block, include)
     */
    private function parseControlTag(string $control): array
    {
        $parts = preg_split('/\s+/', trim($control), 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        return match ($command) {
            'extends' => ['type' => 'extends', 'template' => trim($args, '\'"')],
            'block' => ['type' => 'block', 'name' => trim($args)],
            'endblock' => ['type' => 'endblock'],
            'include' => ['type' => 'include', 'template' => trim($args, '\'"')],
            'if' => ['type' => 'if', 'condition' => $args],
            'endif' => ['type' => 'endif'],
            'for' => ['type' => 'for', 'expression' => $args],
            'endfor' => ['type' => 'endfor'],
            'else' => ['type' => 'else'],
            default => throw new RuntimeException("Unknown control tag: {$command}")
        };
    }

    /**
     * Rendert geparste Tokens mit Block-Replacement für Inheritance
     */
    private function renderParsed(array $tokens): string
    {
        $output = '';
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            switch ($token['type']) {
                case 'text':
                    $output .= $token['content'];
                    break;

                case 'variable':
                    $filters = $token['filters'] ?? [];
                    $output .= $this->renderVariable($token['name'], $filters);
                    break;

                case 'extends':
                    // Skip extends in parent rendering
                    break;

                case 'block':
                    $blockName = $token['name'];

                    if (isset($this->blocks[$blockName])) {
                        // Use child block content (override)
                        error_log("Using child block content for: $blockName");
                        $output .= $this->renderParsed($this->blocks[$blockName]);

                        // Skip to endblock
                        $blockDepth = 1;
                        $skipIndex = $i + 1;
                        while ($skipIndex < $count && $blockDepth > 0) {
                            if ($tokens[$skipIndex]['type'] === 'block') {
                                $blockDepth++;
                            } elseif ($tokens[$skipIndex]['type'] === 'endblock') {
                                $blockDepth--;
                            }
                            $skipIndex++;
                        }
                        $i = $skipIndex - 1; // Will be incremented at end of loop
                    } else {
                        // Use parent block content (default)
                        error_log("Using parent block content for: $blockName");
                        $blockData = $this->extractBlock($tokens, $i);
                        $output .= $this->renderParsed($blockData['content']);
                        $i = $blockData['endIndex'];
                    }
                    break;

                case 'endblock':
                    // Skip standalone endblock (should not happen in normal flow)
                    break;

                case 'include':
                    $output .= $this->renderInclude($token['template']);
                    break;

                case 'if':
                    $ifResult = $this->renderIf($tokens, $i);
                    $output .= $ifResult['output'];
                    $i = $ifResult['endIndex'];
                    break;

                case 'for':
                    $forResult = $this->renderFor($tokens, $i);
                    $output .= $forResult['output'];
                    $i = $forResult['endIndex'];
                    break;
            }

            $i++;
        }

        return $output;
    }

    /**
     * Rendert Variable mit Filter-Support und automatischem XSS-Schutz (UPDATED)
     */
    private function renderVariable(string $name, array $filters = []): string
    {
        error_log("Rendering variable: name='$name', filters=" . json_encode(array_column($filters, 'name')));

        // Handle string literals in quotes
        if ((str_starts_with($name, "'") && str_ends_with($name, "'")) ||
            (str_starts_with($name, '"') && str_ends_with($name, '"'))) {
            $value = substr($name, 1, -1);
            error_log("String literal detected: '$value'");
        } else {
            $value = $this->getValue($name);
            error_log("Variable lookup for '$name': " . json_encode($value));
        }

        if ($value === null) {
            $value = '';
        }

        // Filter anwenden
        foreach ($filters as $filter) {
            $filterName = $filter['name'];
            $parameters = $filter['parameters'];

            error_log("Applying filter '$filterName' with params: " . json_encode($parameters));

            try {
                $value = $this->filterManager->apply($filterName, $value, $parameters);
                error_log("Filter result: '$value'");
            } catch (\Throwable $e) {
                error_log("Filter error: {$e->getMessage()}");
            }
        }

        // *** XSS-SCHUTZ: Automatisches HTML-Escaping ***
        if ($this->autoEscape && is_string($value)) {
            // Prüfe ob der 'raw' Filter verwendet wurde
            $hasRawFilter = false;
            foreach ($filters as $filter) {
                if ($filter['name'] === 'raw') {
                    $hasRawFilter = true;
                    break;
                }
            }

            // Nur escapen wenn kein 'raw' Filter vorhanden ist
            if (!$hasRawFilter) {
                $value = htmlspecialchars(
                    $value,
                    ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
                    'UTF-8',
                    true
                );
            }
        }

        return (string)$value;
    }

    /**
     * Holt Wert mit Dot-Notation (z.B. user.name) - KORRIGIERT
     */
    private function getValue(string $name): mixed
    {
        if (str_contains($name, '.')) {
            $parts = explode('.', $name);
            $current = $this->data;

            foreach ($parts as $part) {
                if (is_array($current) && isset($current[$part])) {
                    $current = $current[$part];
                } elseif (is_object($current) && isset($current->$part)) {
                    $current = $current->$part;
                } else {
                    return null;
                }
            }

            return $current;
        }

        return $this->data[$name] ?? null;
    }

    /**
     * Extrahiert Block-Content zwischen block und endblock
     */
    private function extractBlock(array $tokens, int $startIndex): array
    {
        $content = [];
        $blockDepth = 1;
        $i = $startIndex + 1;

        while ($i < count($tokens) && $blockDepth > 0) {
            $token = $tokens[$i];

            if ($token['type'] === 'block') {
                $blockDepth++;
            } elseif ($token['type'] === 'endblock') {
                $blockDepth--;
                if ($blockDepth === 0) {
                    break;
                }
            }

            $content[] = $token;
            $i++;
        }

        return ['content' => $content, 'endIndex' => $i];
    }

    /**
     * Rendert Include mit Dependency Tracking
     */
    private function renderInclude(string $template): string
    {
        $originalData = $this->data; // Backup data

        $includePath = $this->findTemplate($template);

        // Track dependency
        if (!in_array($includePath, $this->loadedTemplates)) {
            $this->loadedTemplates[] = $includePath;
        }

        $includeContent = file_get_contents($includePath);
        $parsed = $this->parseTemplate($includeContent);

        $result = $this->renderParsed($parsed);

        $this->data = $originalData; // Restore data
        return $result;
    }

    /**
     * Rendert IF-Bedingung
     */
    private function renderIf(array $tokens, int $startIndex): array
    {
        $condition = $tokens[$startIndex]['condition'];
        $conditionResult = $this->evaluateCondition($condition);

        $output = '';
        $i = $startIndex + 1;
        $ifDepth = 1;
        $inElse = false;

        while ($i < count($tokens) && $ifDepth > 0) {
            $token = $tokens[$i];

            if ($token['type'] === 'if') {
                $ifDepth++;
            } elseif ($token['type'] === 'endif') {
                $ifDepth--;
                if ($ifDepth === 0) {
                    break;
                }
            } elseif ($token['type'] === 'else' && $ifDepth === 1) {
                $inElse = true;
                $i++;
                continue;
            }

            // Render content based on condition
            if (($conditionResult && !$inElse) || (!$conditionResult && $inElse)) {
                if ($token['type'] === 'text') {
                    $output .= $token['content'];
                } elseif ($token['type'] === 'variable') {
                    $filters = $token['filters'] ?? [];
                    $output .= $this->renderVariable($token['name'], $filters);
                } elseif ($token['type'] === 'if') {
                    // Handle nested if
                    $nestedResult = $this->renderIf($tokens, $i);
                    $output .= $nestedResult['output'];
                    $i = $nestedResult['endIndex'];
                } elseif ($token['type'] === 'for') {
                    // Handle nested for
                    $nestedResult = $this->renderFor($tokens, $i);
                    $output .= $nestedResult['output'];
                    $i = $nestedResult['endIndex'];
                } elseif ($token['type'] === 'include') {
                    $output .= $this->renderInclude($token['template']);
                }
            }

            $i++;
        }

        return ['output' => $output, 'endIndex' => $i];
    }

    /**
     * Einfache Bedingungsauswertung
     */
    private function evaluateCondition(string $condition): bool
    {
        $condition = trim($condition);

        // Simple variable check (user.isAdmin)
        if (!str_contains($condition, ' ')) {
            $value = $this->getValue($condition);
            return !empty($value);
        }

        // Handle basic comparisons
        if (preg_match('/(.+?)\s*(==|!=|>|<|>=|<=)\s*(.+)/', $condition, $matches)) {
            $left = trim($matches[1]);
            $operator = $matches[2];
            $right = trim($matches[3], '\'"');

            $leftValue = $this->getValue($left);

            return match ($operator) {
                '==' => $leftValue == $right,
                '!=' => $leftValue != $right,
                '>' => $leftValue > $right,
                '<' => $leftValue < $right,
                '>=' => $leftValue >= $right,
                '<=' => $leftValue <= $right,
                default => false
            };
        }

        return false;
    }

    /**
     * Rendert FOR-Schleife
     */
    private function renderFor(array $tokens, int $startIndex): array
    {
        $expression = $tokens[$startIndex]['expression'];

        // Parse both syntaxes: "item in items" and "items as item"
        if (preg_match('/(\w+)\s+in\s+([\w.]+)/', $expression, $matches)) {
            // Standard syntax: item in items
            $itemVar = $matches[1];
            $arrayVar = $matches[2];
        } elseif (preg_match('/([\w.]+)\s+as\s+(\w+)/', $expression, $matches)) {
            // Laravel/Twig syntax: items as item
            $arrayVar = $matches[1];
            $itemVar = $matches[2];
        } else {
            throw new RuntimeException("Invalid for loop syntax: {$expression}. Use 'item in items' or 'items as item'");
        }

        $array = $this->getValue($arrayVar);

        if (!is_array($array)) {
            return ['output' => '', 'endIndex' => $this->findEndFor($tokens, $startIndex)];
        }

        $output = '';
        $loopContent = $this->extractForContent($tokens, $startIndex);

        foreach ($array as $item) {
            // Backup current data
            $originalData = $this->data;

            // Add loop item to data
            $this->data[$itemVar] = $item;

            // Render loop content
            $output .= $this->renderParsed($loopContent['content']);

            // Restore original data
            $this->data = $originalData;
        }

        return ['output' => $output, 'endIndex' => $loopContent['endIndex']];
    }

    /**
     * Findet endfor Position
     */
    private function findEndFor(array $tokens, int $startIndex): int
    {
        $forDepth = 1;
        $i = $startIndex + 1;

        while ($i < count($tokens) && $forDepth > 0) {
            $token = $tokens[$i];

            if ($token['type'] === 'for') {
                $forDepth++;
            } elseif ($token['type'] === 'endfor') {
                $forDepth--;
            }

            $i++;
        }

        return $i - 1;
    }

    /**
     * Extrahiert FOR-Content
     */
    private function extractForContent(array $tokens, int $startIndex): array
    {
        $content = [];
        $forDepth = 1;
        $i = $startIndex + 1;

        while ($i < count($tokens) && $forDepth > 0) {
            $token = $tokens[$i];

            if ($token['type'] === 'for') {
                $forDepth++;
            } elseif ($token['type'] === 'endfor') {
                $forDepth--;
                if ($forDepth === 0) {
                    break;
                }
            }

            $content[] = $token;
            $i++;
        }

        return ['content' => $content, 'endIndex' => $i];
    }

    /**
     * First pass: Extract extends and blocks from child template
     */
    private function extractExtendsAndBlocks(array $tokens): void
    {
        $i = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token['type'] === 'extends') {
                $this->parentTemplate = $token['template'];
                error_log("Found extends: " . $this->parentTemplate);
            } elseif ($token['type'] === 'block') {
                $blockName = $token['name'];
                $blockData = $this->extractBlock($tokens, $i);
                $this->blocks[$blockName] = $blockData['content'];
                error_log("Extracted child block '$blockName' with " . count($blockData['content']) . " tokens");
                $i = $blockData['endIndex'];
            }

            $i++;
        }
    }

    /**
     * *** NEUE METHODE: Auto-Escape Check ***
     */
    public function isAutoEscapeEnabled(): bool
    {
        return $this->autoEscape;
    }

    /**
     * Fügt Template-Pfad hinzu
     */
    public function addPath(string $path): void
    {
        $this->paths[] = $path;
    }

    /**
     * Holt alle Template-Pfade
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Holt Filter Manager
     */
    public function getFilterManager(): FilterManager
    {
        return $this->filterManager;
    }

    /**
     * Get template cache
     */
    public function getCache(): TemplateCache
    {
        return $this->cache;
    }

    /**
     * Set template cache
     */
    public function setCache(TemplateCache $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Clear template cache
     */
    public function clearCache(): int
    {
        return $this->cache->clearAll();
    }

    /**
     * Clear compiled cache (für Development)
     */
    public function clearCompiledCache(): void
    {
        $this->cache->clearAll();
        error_log("Template cache cleared");
    }

    /**
     * Debug-Information mit Cache-Details
     */
    public function getDebugInfo(): array
    {
        return [
            'paths' => $this->paths,
            'parent_template' => $this->parentTemplate,
            'blocks' => array_keys($this->blocks),
            'data_keys' => array_keys($this->data),
            'available_filters' => $this->filterManager->getAvailableFilters(),
            'loaded_filters' => $this->filterManager->getLoadedFilters(),
            'filter_stats' => [
                'available' => count($this->filterManager->getAvailableFilters()),
                'loaded' => count($this->filterManager->getLoadedFilters()),
                'lazy_remaining' => count($this->filterManager->getAvailableFilters()) - count($this->filterManager->getLoadedFilters())
            ],
            'cache_stats' => $this->getCacheStats(),
            'loaded_templates' => $this->loadedTemplates,
        ];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return $this->cache->getStats();
    }

    /**
     * Parse cache directives from template
     */
    private function parseCacheDirectives(array $tokens): array
    {
        $directives = ['ttl' => 0, 'tags' => []];

        foreach ($tokens as $token) {
            if ($token['type'] === 'control') {
                $control = $token['content'] ?? '';

                // Parse {% cache ttl:300 tags:player,team %}
                if (str_starts_with($control, 'cache ')) {
                    if (preg_match('/ttl:(\d+)/', $control, $matches)) {
                        $directives['ttl'] = (int)$matches[1];
                    }
                    if (preg_match('/tags:([a-zA-Z0-9,_-]+)/', $control, $matches)) {
                        $directives['tags'] = array_map('trim', explode(',', $matches[1]));
                    }
                }
            }
        }

        return $directives;
    }

    /**
     * HTML-Escaping für XSS-Schutz
     */
    private function escapeHtml(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE,
            'UTF-8',
            true
        );
    }
}