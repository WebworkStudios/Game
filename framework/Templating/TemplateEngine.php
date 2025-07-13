<?php

declare(strict_types=1);

namespace Framework\Templating;

use RuntimeException;

/**
 * Template Engine - Twig-ähnliche Syntax mit Variables, Controls, Inheritance, Filtern, Caching und Fragment-Support
 *
 * Optimized Version - Step 1: Code Structure Improvements
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
    private bool $autoEscape = true;

    public function __construct(
        array          $templatePaths = [],
        ?TemplateCache $cache = null,
        bool           $autoEscape = true
    )
    {
        $this->paths = $templatePaths;
        $this->filterManager = new FilterManager();
        $this->cache = $cache ?? new TemplateCache(sys_get_temp_dir() . '/template_cache', false);
        $this->autoEscape = $autoEscape;
    }

    /**
     * Add template path
     */
    public function addPath(string $path): void
    {
        if (!in_array($path, $this->paths)) {
            $this->paths[] = $path;
        }
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
     * Main render method - simplified flow
     */
    public function render(string $template, array $data = []): string
    {
        $this->data = $data;
        $this->blocks = [];
        $this->parentTemplate = null;
        $this->loadedTemplates = [];

        try {
            $templatePath = $this->findTemplate($template);
            $this->loadedTemplates[] = $templatePath;

            // Try cache first
            if ($this->cache->isValid($template, $this->loadedTemplates)) {
                $compiled = $this->cache->load($template);
                if ($compiled !== null) {
                    return $this->renderCompiled($compiled);
                }
            }

            // Parse and cache
            $content = file_get_contents($templatePath);
            if (strlen($content) === 0) {
                throw new RuntimeException("Template file is empty: $templatePath");
            }

            $parsed = $this->parseTemplate($content);
            $this->extractExtendsAndBlocks($parsed);

            $compiled = [
                'tokens' => $parsed,
                'blocks' => $this->blocks,
                'parent_template' => $this->parentTemplate,
                'template_path' => $templatePath,
                'dependencies' => $this->loadedTemplates,
            ];

            $this->cache->store($template, $templatePath, $compiled, $this->loadedTemplates);

            return $this->renderCompiled($compiled);

        } catch (\Throwable $e) {
            throw new RuntimeException("Template rendering failed: {$template}. Error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse template content into token structure - OPTIMIZED
     */
    private function parseTemplate(string $content): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $nextTag = $this->findNextTag($content, $offset);

            if ($nextTag === null) {
                // Rest as text
                if ($offset < $length) {
                    $tokens[] = [
                        'type' => 'text',
                        'content' => substr($content, $offset)
                    ];
                }
                break;
            }

            // Add text before tag
            if ($nextTag['position'] > $offset) {
                $tokens[] = [
                    'type' => 'text',
                    'content' => substr($content, $offset, $nextTag['position'] - $offset)
                ];
            }

            // Parse the tag
            $tagToken = $this->parseTag($content, $nextTag);
            if ($tagToken !== null) {
                $tokens[] = $tagToken;
            }

            $offset = $nextTag['end'];
        }

        return $tokens;
    }

    /**
     * Find next template tag (variable or control) - NEW
     */
    private function findNextTag(string $content, int $offset): ?array
    {
        $length = strlen($content);
        $varStart = strpos($content, '{{', $offset);
        $controlStart = strpos($content, '{%', $offset);

        $nextTag = null;
        $nextPos = $length;

        if ($varStart !== false && ($controlStart === false || $varStart < $controlStart)) {
            $nextTag = 'variable';
            $nextPos = $varStart;
        } elseif ($controlStart !== false) {
            $nextTag = 'control';
            $nextPos = $controlStart;
        }

        if ($nextTag === null) {
            return null;
        }

        return [
            'type' => $nextTag,
            'position' => $nextPos,
            'end' => 0 // Will be set in parseTag
        ];
    }

    /**
     * Parse a specific tag (variable or control) - NEW
     */
    private function parseTag(string $content, array &$tagInfo): ?array
    {
        if ($tagInfo['type'] === 'variable') {
            return $this->parseVariableTag($content, $tagInfo);
        } else {
            return $this->parseControlTag($content, $tagInfo);
        }
    }

    /**
     * Parse variable tag {{ variable | filter }} - NEW
     */
    private function parseVariableTag(string $content, array &$tagInfo): ?array
    {
        $varEnd = strpos($content, '}}', $tagInfo['position']);
        if ($varEnd === false) {
            return null;
        }

        $tagInfo['end'] = $varEnd + 2;
        $expression = trim(substr($content, $tagInfo['position'] + 2, $varEnd - $tagInfo['position'] - 2));

        // Use existing parseVariableWithFilters method
        return $this->parseVariableWithFilters($expression);
    }

    /**
     * Parse control tag {% if/for/block/etc %} - NEW
     */
    private function parseControlTag(string $content, array &$tagInfo): ?array
    {
        $controlEnd = strpos($content, '%}', $tagInfo['position']);
        if ($controlEnd === false) {
            return null;
        }

        $tagInfo['end'] = $controlEnd + 2;
        $expression = trim(substr($content, $tagInfo['position'] + 2, $controlEnd - $tagInfo['position'] - 2));

        return $this->parseControlExpression($expression);
    }

    /**
     * Parse control expression and return appropriate token - NEW
     */
    private function parseControlExpression(string $expression): ?array
    {
        $parts = explode(' ', $expression, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        return match ($command) {
            'if' => ['type' => 'if', 'condition' => $args],
            'endif' => ['type' => 'endif'],
            'else' => ['type' => 'else'],
            'for' => ['type' => 'for', 'expression' => $args],
            'endfor' => ['type' => 'endfor'],
            'block' => ['type' => 'block', 'name' => trim($args)],
            'endblock' => ['type' => 'endblock'],
            'extends' => ['type' => 'extends', 'template' => trim($args, '\'"')],
            'include' => ['type' => 'include', 'template' => trim($args, '\'"')],
            default => null
        };
    }

    /**
     * Parse variable with filters (zurück zur ORIGINALEN Logik)
     */
    private function parseVariableWithFilters(string $expression): array
    {
        // Return the parsed structure directly without additional wrapping
        return $this->parseVariableExpression($expression);
    }

    /**
     * Parse variable expression (ORIGINALE Methode)
     */
    private function parseVariableExpression(string $expression): array
    {
        // Check for filters first: variable|filter:param1:param2
        if (str_contains($expression, '|')) {
            $parts = explode('|', $expression, 2);
            $variableExpression = trim($parts[0]);
            $filterChain = trim($parts[1]);

            // Parse the variable part (might contain math)
            $variableData = $this->parseVariablePart($variableExpression);

            // Parse filters
            $filters = $this->parseFilterChain($filterChain);

            return [
                'type' => 'variable',
                'variable_data' => $variableData,
                'filters' => $filters
            ];
        }

        // No filters, just parse the variable part
        $variableData = $this->parseVariablePart($expression);

        return [
            'type' => 'variable',
            'variable_data' => $variableData
        ];
    }

    /**
     * Parse variable part (simple or math expressions)
     */
    private function parseVariablePart(string $expression): array
    {
        $expression = trim($expression);

        // Check for parentheses with math operations
        if (preg_match('/^\(([^)]+)\)$/', $expression, $matches)) {
            $innerExpression = $matches[1];

            // Check for mathematical operations
            if (preg_match('#^(.+?)\s*([+\-*/])\s*(.+)$#', $innerExpression, $mathMatches)) {
                return [
                    'type' => 'math',
                    'left' => trim($mathMatches[1]),
                    'operator' => trim($mathMatches[2]),
                    'right' => trim($mathMatches[3])
                ];
            }
        }

        // Simple variable access
        return [
            'type' => 'simple',
            'name' => $expression
        ];
    }

    /**
     * Parse filter chain (existing method - corrected)
     */
    private function parseFilterChain(string $filterChain): array
    {
        $filters = [];

        // Split by pipe, but be careful about pipes within parameters
        $filterParts = $this->splitFilterChain($filterChain);

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
     * Split filter chain while respecting nested parameters
     */
    private function splitFilterChain(string $filterChain): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($filterChain);

        for ($i = 0; $i < $length; $i++) {
            $char = $filterChain[$i];

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            } elseif ($char === '|' && $depth === 0) {
                // Found a filter separator at top level
                if ($current !== '') {
                    $parts[] = trim($current);
                    $current = '';
                }
                continue;
            }

            $current .= $char;
        }

        // Add the last part
        if ($current !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Parse filter parameters (existing method - unchanged)
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
     * Parse object-style parameters (existing method - unchanged)
     */
    private function parseObjectParameters(string $objectString): array
    {
        $parameters = [];
        $content = trim($objectString, '{}');

        if (empty($content)) {
            return [];
        }

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

        return $parameters;
    }

    /**
     * Render parsed tokens - OPTIMIZED
     */
    private function renderParsed(array $tokens): string
    {
        $output = '';
        $i = 0;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if (in_array($token['type'], ['if', 'for'])) {
                $result = $this->renderControlFlow($tokens, $i);
                $output .= $result['output'];
                $i = $result['endIndex'];
            } else {
                $output .= $this->renderSingleToken($tokens, $i, $token);
            }

            $i++;
        }

        return $output;
    }

    /**
     * Unified rendering for control flow (if/for loops) - NEW
     */
    private function renderControlFlow(array $tokens, int $startIndex): array
    {
        $token = $tokens[$startIndex];

        return match ($token['type']) {
            'if' => $this->renderIfBlock($tokens, $startIndex),
            'for' => $this->renderForBlock($tokens, $startIndex),
            default => ['output' => '', 'endIndex' => $startIndex]
        };
    }

    /**
     * Render IF block with unified token handling - OPTIMIZED
     */
    private function renderIfBlock(array $tokens, int $startIndex): array
    {
        $condition = $tokens[$startIndex]['condition'];
        $output = '';
        $i = $startIndex + 1;
        $inElse = false;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token['type'] === 'endif') {
                break;
            }

            if ($token['type'] === 'else') {
                $inElse = true;
                $i++;
                continue;
            }

            $shouldRender = $inElse ? !$this->evaluateCondition($condition) : $this->evaluateCondition($condition);

            if ($shouldRender) {
                if (in_array($token['type'], ['if', 'for'])) {
                    $nestedResult = $this->renderControlFlow($tokens, $i);
                    $output .= $nestedResult['output'];
                    $i = $nestedResult['endIndex'];
                } else {
                    $output .= $this->renderSingleToken($tokens, $i, $token);
                }
            }

            $i++;
        }

        return ['output' => $output, 'endIndex' => $i];
    }

    /**
     * Render FOR block with unified token handling - OPTIMIZED
     */
    private function renderForBlock(array $tokens, int $startIndex): array
    {
        $expression = $tokens[$startIndex]['expression'];
        $output = '';

        // Parse loop syntax
        $loopData = $this->parseLoopExpression($expression);
        if ($loopData === null) {
            return ['output' => '', 'endIndex' => $this->findEndToken($tokens, $startIndex, 'endfor')];
        }

        $items = $this->getValue($loopData['collection']);
        if (!is_array($items)) {
            return ['output' => '', 'endIndex' => $this->findEndToken($tokens, $startIndex, 'endfor')];
        }

        // Store original data
        $originalData = $this->data;

        foreach ($items as $key => $item) {
            $this->data[$loopData['variable']] = $item;
            $this->data['loop'] = ['index' => $key, 'first' => $key === 0];

            $i = $startIndex + 1;
            while ($i < count($tokens)) {
                $token = $tokens[$i];

                if ($token['type'] === 'endfor') {
                    break;
                }

                if (in_array($token['type'], ['if', 'for'])) {
                    $nestedResult = $this->renderControlFlow($tokens, $i);
                    $output .= $nestedResult['output'];
                    $i = $nestedResult['endIndex'];
                } else {
                    $output .= $this->renderSingleToken($tokens, $i, $token);
                }

                $i++;
            }
        }

        // Restore original data
        $this->data = $originalData;

        return ['output' => $output, 'endIndex' => $this->findEndToken($tokens, $startIndex, 'endfor')];
    }

    /**
     * Render single token (unified for if/for/normal rendering) - FIXED
     */
    private function renderSingleToken(array $tokens, int &$index, array $token): string
    {
        return match ($token['type']) {
            'text' => $token['content'],
            'variable' => $this->renderVariable($token, []), // Token selbst enthält bereits alle Daten
            'include' => $this->renderInclude($token['template']),
            'block' => $this->renderBlock($token['name'], $tokens, $index),
            default => ''
        };
    }

    /**
     * Parse loop expression (both "item in items" and "items as item") - NEW
     */
    private function parseLoopExpression(string $expression): ?array
    {
        if (preg_match('/(\w+)\s+in\s+([\w.]+)/', $expression, $matches)) {
            return ['variable' => $matches[1], 'collection' => $matches[2]];
        }

        if (preg_match('/([\w.]+)\s+as\s+(\w+)/', $expression, $matches)) {
            return ['variable' => $matches[2], 'collection' => $matches[1]];
        }

        return null;
    }

    /**
     * Find end token for control structures - NEW
     */
    private function findEndToken(array $tokens, int $startIndex, string $endType): int
    {
        $i = $startIndex + 1;
        $nested = 0;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token['type'] === $endType && $nested === 0) {
                return $i;
            }

            // Track nesting
            if (in_array($token['type'], ['if', 'for'])) {
                $nested++;
            } elseif (in_array($token['type'], ['endif', 'endfor'])) {
                $nested--;
            }

            $i++;
        }

        return $i;
    }

    /**
     * Render variable with filters
     */
    private function renderVariable(mixed $token, array $filters = []): string
    {
        // Handle different token structures for backward compatibility
        if (is_array($token) && isset($token['variable_data'])) {
            $variableData = $token['variable_data'];
            $filters = $token['filters'] ?? [];
        } elseif (is_array($token) && isset($token['name'])) {
            // Old structure compatibility
            $variableData = ['type' => 'simple', 'name' => $token['name']];
            $filters = $token['filters'] ?? $filters;
        } elseif (is_string($token)) {
            // Direct string variable name
            $variableData = ['type' => 'simple', 'name' => $token];
        } else {
            return '';
        }

        // Get the base value
        if ($variableData['type'] === 'math') {
            $leftValue = $this->getValue($variableData['left']);
            $rightValue = $this->getValue($variableData['right']);

            $value = match ($variableData['operator']) {
                '+' => $leftValue + $rightValue,
                '-' => $leftValue - $rightValue,
                '*' => $leftValue * $rightValue,
                '/' => $rightValue != 0 ? $leftValue / $rightValue : 0,
                default => 0
            };
        } else {
            $value = $this->getValue($variableData['name']);
        }

        // Apply filters
        if (!empty($filters)) {
            foreach ($filters as $filter) {
                if (is_array($filter)) {
                    $filterName = $filter['name'];
                    $parameters = $filter['parameters'] ?? [];
                } else {
                    $filterName = $filter;
                    $parameters = [];
                }

                $value = $this->filterManager->apply($filterName, $value, $parameters);
            }
        }

        // XSS Protection: Auto-escape unless 'raw' filter is used
        if ($this->autoEscape && !$this->hasRawFilter($filters)) {
            $value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return (string)$value;
    }

    /**
     * Get value from data using dot notation
     */
    private function getValue(string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;

        foreach ($keys as $keyPart) {
            if (is_array($value) && array_key_exists($keyPart, $value)) {
                $value = $value[$keyPart];
            } elseif (is_object($value) && property_exists($value, $keyPart)) {
                $value = $value->$keyPart;
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Check if filters contain 'raw' filter
     */
    private function hasRawFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if (is_array($filter) && ($filter['name'] ?? '') === 'raw') {
                return true;
            } elseif (is_string($filter) && $filter === 'raw') {
                return true;
            }
        }
        return false;
    }

    /**
     * Evaluate condition for if statements
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
     * Extract extends and blocks from tokens
     */
    private function extractExtendsAndBlocks(array $tokens): void
    {
        $i = 0;
        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token['type'] === 'extends') {
                $this->parentTemplate = $token['template'];
            } elseif ($token['type'] === 'block') {
                $blockName = $token['name'];
                $blockData = $this->extractBlock($tokens, $i);
                $this->blocks[$blockName] = $blockData['content'];
                $i = $blockData['endIndex'];
            }

            $i++;
        }
    }

    /**
     * Extract block content
     */
    private function extractBlock(array $tokens, int $startIndex): array
    {
        $content = [];
        $i = $startIndex + 1;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token['type'] === 'endblock') {
                break;
            }

            $content[] = $token;
            $i++;
        }

        return ['content' => $content, 'endIndex' => $i];
    }

    /**
     * Render block - FIXED for inheritance
     */
    private function renderBlock(string $blockName, array $tokens, int &$index): string
    {
        // Extract the block content from parent template
        $blockData = $this->extractBlock($tokens, $index);
        $index = $blockData['endIndex'];

        // If we have a child block override, use it instead of parent block
        if (isset($this->blocks[$blockName])) {
            return $this->renderParsed($this->blocks[$blockName]);
        }

        // Otherwise use the parent block content
        return $this->renderParsed($blockData['content']);
    }

    /**
     * Render include
     */
    private function renderInclude(string $template): string
    {
        try {
            $engine = new self($this->paths, $this->cache, $this->autoEscape);
            return $engine->render($template, $this->data);
        } catch (\Throwable $e) {
            return "<!-- Include error: {$template} - {$e->getMessage()} -->";
        }
    }

    /**
     * Find template file
     */
    private function findTemplate(string $template): string
    {
        // Add .html extension if not present
        if (!str_contains($template, '.')) {
            $template .= '.html';
        }

        foreach ($this->paths as $path) {
            // Normalize path separators
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            $normalizedTemplate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template);

            $fullPath = rtrim($normalizedPath, DIRECTORY_SEPARATOR) .
                DIRECTORY_SEPARATOR .
                ltrim($normalizedTemplate, DIRECTORY_SEPARATOR);

            if (file_exists($fullPath)) {
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
            return $this->renderWithInheritance();
        } else {
            return $this->renderParsed($tokens);
        }
    }

    /**
     * Render with inheritance - FIXED
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

        // Store current child blocks
        $childBlocks = $this->blocks;

        // Extract parent blocks (but don't override child blocks yet)
        $this->extractExtendsAndBlocks($parentTokens);

        // Child blocks override parent blocks
        $this->blocks = array_merge($this->blocks, $childBlocks);

        // Render parent with child blocks available
        return $this->renderParsed($parentTokens);
    }
    /**
     * Clear cache
     */
    public function clearCache(): int
    {
        return $this->cache->clearAll();
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return $this->cache->getStats();
    }

    /**
     * Invalidate cache by tag
     */
    public function invalidateByTag(string $tag): int
    {
        return $this->cache->invalidateByTag($tag);
    }

    /**
     * Invalidate cache by multiple tags
     */
    public function invalidateByTags(array $tags): int
    {
        return $this->cache->invalidateByTags($tags);
    }
}