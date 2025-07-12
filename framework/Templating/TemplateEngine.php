<?php
// framework/Templating/TemplateEngine.php

declare(strict_types=1);

namespace Framework\Templating;

use RuntimeException;

/**
 * Template Engine - Twig-ähnliche Syntax mit Variables, Controls, Inheritance und Filtern
 */
class TemplateEngine
{
    private array $paths = [];
    private array $data = [];
    private array $blocks = [];
    private ?string $parentTemplate = null;
    private FilterManager $filterManager;

    public function __construct(array $templatePaths = [])
    {
        $this->paths = $templatePaths;
        $this->filterManager = new FilterManager();
    }

    /**
     * Rendert Template mit Daten
     */
    public function render(string $template, array $data = []): string
    {
        error_log("=== TemplateEngine::render START ===");
        error_log("Template: $template");

        $this->data = $data;
        $this->blocks = [];
        $this->parentTemplate = null;

        try {
            $templatePath = $this->findTemplate($template);
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

            // Handle inheritance
            if ($this->parentTemplate) {
                error_log("Using inheritance");
                $result = $this->renderWithInheritance();
            } else {
                error_log("Rendering without inheritance");
                $result = $this->renderParsed($parsed);
            }

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
        // Prüfe auf Filter-Syntax: variable|filter:param1:param2
        if (!str_contains($expression, '|')) {
            return ['type' => 'variable', 'name' => $expression];
        }

        $parts = explode('|', $expression, 2);
        $variableName = trim($parts[0]);
        $filterChain = trim($parts[1]);

        // Parse Filter-Chain
        $filters = $this->parseFilterChain($filterChain);

        return [
            'type' => 'variable',
            'name' => $variableName,
            'filters' => $filters
        ];
    }

    /**
     * Parst Filter-Chain
     */
    private function parseFilterChain(string $filterChain): array
    {
        $filters = [];
        $filterParts = explode('|', $filterChain);

        foreach ($filterParts as $filterExpr) {
            $filterExpr = trim($filterExpr);

            if (str_contains($filterExpr, ':')) {
                $parts = explode(':', $filterExpr);
                $filterName = array_shift($parts);
                $parameters = array_map('trim', $parts);

                // Remove quotes from string parameters
                $parameters = array_map(function ($param) {
                    return trim($param, '\'"');
                }, $parameters);
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
     * Render with inheritance - load parent and inject blocks
     */
    private function renderWithInheritance(): string
    {
        // Load parent template
        $parentPath = $this->findTemplate($this->parentTemplate);
        $parentContent = file_get_contents($parentPath);
        $parentTokens = $this->parseTemplate($parentContent);

        // Render parent with child blocks
        return $this->renderParsed($parentTokens);
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
     * Rendert Variable mit Filter-Support
     */
    private function renderVariable(string $name, array $filters = []): string
    {
        $value = $this->getValue($name);

        if ($value === null) {
            $value = '';
        }

        // Wende Filter an
        foreach ($filters as $filter) {
            $filterName = $filter['name'];
            $parameters = $filter['parameters'];

            try {
                $value = $this->filterManager->apply($filterName, $value, $parameters);
            } catch (\Throwable $e) {
                error_log("Filter error: {$e->getMessage()}");
                // Bei Filter-Fehlern: Original-Wert beibehalten
            }
        }

        // Raw-Filter überspringt HTML-Escaping
        $hasRawFilter = false;
        foreach ($filters as $filter) {
            if ($filter['name'] === 'raw') {
                $hasRawFilter = true;
                break;
            }
        }

        if (!$hasRawFilter) {
            $value = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }

        return (string)$value;
    }

    /**
     * Holt Wert mit Dot-Notation (z.B. user.name)
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
     * Rendert Include
     */
    private function renderInclude(string $template): string
    {
        $originalData = $this->data; // Backup data

        $includePath = $this->findTemplate($template);
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

        // Parse "item in items" syntax
        if (!preg_match('/(\w+)\s+in\s+([\w.]+)/', $expression, $matches)) {
            throw new RuntimeException("Invalid for loop syntax: {$expression}");
        }

        $itemVar = $matches[1];
        $arrayVar = $matches[2];
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
     * Clear compiled cache (für Development)
     */
    public function clearCompiledCache(): void
    {
        // Placeholder für Caching-Feature (Schritt 6)
        error_log("Template cache cleared (placeholder)");
    }

    /**
     * Debug-Information
     */
    public function getDebugInfo(): array
    {
        return [
            'paths' => $this->paths,
            'parent_template' => $this->parentTemplate,
            'blocks' => array_keys($this->blocks),
            'data_keys' => array_keys($this->data),
            'available_filters' => $this->filterManager->getRegisteredFilters(),
        ];
    }
}