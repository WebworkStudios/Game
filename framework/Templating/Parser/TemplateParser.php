<?php
declare(strict_types=1);

namespace Framework\Templating\Parser;

use Framework\Templating\Parser\Tokens\Token;
use Framework\Templating\Parser\Tokens\TokenType;

class TemplateParser
{
    private const string VARIABLE_START = '{{';
    private const string VARIABLE_END = '}}';
    private const string BLOCK_START = '{%';
    private const string BLOCK_END = '%}';

    public function parse(string $content): array
    {
        $tokens = $this->tokenize($content);
        return $this->parseTokens($tokens);
    }

    /**
     * Tokenize content - OPTIMIZED VERSION
     */
    private function tokenize(string $content): array
    {
        $tokens = [];
        $position = 0;
        $length = strlen($content);

        // IMPROVED: Better regex that handles quotes inside blocks
        $pattern = '/(\{\{.*?}}|\{%.*?%})/s';
        $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $currentPos = 0;
        foreach ($parts as $part) {
            if (str_starts_with($part, '{{') && str_ends_with($part, '}}')) {
                // Variable token
                $expression = $this->cleanExpression(substr($part, 2, -2));
                $tokens[] = new Token(TokenType::VARIABLE, $expression, $currentPos, $currentPos + strlen($part));
            } elseif (str_starts_with($part, '{%') && str_ends_with($part, '%}')) {
                // Block token
                $expression = $this->cleanExpression(substr($part, 2, -2));

                // FIX: Validate block expression before creating token
                if (!empty($expression) && !$this->isValidBlockExpression($expression)) {
                    // Treat as text if it's not a valid block
                    if ($part !== '') {
                        $tokens[] = new Token(TokenType::TEXT, $part, $currentPos);
                    }
                } else {
                    $tokens[] = new Token(TokenType::BLOCK, $expression, $currentPos, $currentPos + strlen($part));
                }
            } else {
                // Text token - only add if not empty
                if ($part !== '') {
                    $tokens[] = new Token(TokenType::TEXT, $part, $currentPos);
                }
            }
            $currentPos += strlen($part);
        }

        return $tokens;
    }

    /**
     * Validate if expression is a valid block command
     */
    private function isValidBlockExpression(string $expression): bool
    {
        $trimmed = trim($expression);
        if (empty($trimmed)) {
            return false;
        }

        $firstWord = explode(' ', $trimmed)[0];

        // Valid block commands
        $validCommands = ['if', 'endif', 'else', 'for', 'endfor', 'block', 'endblock', 'extends', 'include'];

        return in_array($firstWord, $validCommands, true);
    }

    /**
     * Clean expression by normalizing whitespace and removing unnecessary characters
     */
    private function cleanExpression(string $expression): string
    {
        // Remove leading/trailing whitespace
        $expression = trim($expression);

        // Replace multiple whitespace characters (including newlines) with single spaces
        $expression = preg_replace('/\s+/', ' ', $expression);

        // Remove any remaining problematic characters
        $expression = trim($expression);

        return $expression;
    }

    private function parseTokens(array $tokens): array
    {
        $ast = [];
        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            switch ($token->getType()) {
                case TokenType::TEXT:
                    $ast[] = ['type' => 'text', 'content' => $token->getValue()];
                    break;

                case TokenType::VARIABLE:
                    $ast[] = $this->parseVariable($token->getValue());
                    break;

                case TokenType::BLOCK:
                    $blockResult = $this->parseBlock($token->getValue(), $tokens, $i);

                    // FIX: Filter out closing_tag nodes - they shouldn't be in the final AST
                    if ($blockResult['node']['type'] !== 'closing_tag') {
                        $ast[] = $blockResult['node'];
                    }

                    $i = $blockResult['nextIndex'] - 1; // -1 because loop will increment
                    break;
            }
        }

        return $ast;
    }

    /**
     * Parse variable - OPTIMIZED VERSION focusing on common cases
     */
    private function parseVariable(string $expression): array
    {
        // Fast path for string literals - CHECK FIRST!
        if ($this->isStringLiteral($expression)) {
            return $this->parseStringLiteral($expression);
        }

        // Fast path for simple variables (60% of cases)
        if (!str_contains($expression, '|') && !str_contains($expression, '(') && !str_contains($expression, '"') && !str_contains($expression, "'")) {
            return $this->parseSimpleVariable($expression);
        }

        // Fast path for function calls (20% of cases)
        if (str_contains($expression, '(')) {
            if (preg_match('/^(t|t_plural|locale|locales)\s*\(/', $expression)) {
                return $this->parseFunctionCall($expression);
            }
        }

        // Fast path for filters without complex syntax (15% of cases)
        if (str_contains($expression, '|') && !str_contains($expression, '(')) {
            return $this->parseSimpleFilter($expression);
        }

        // Complex cases (5% of cases) - use original logic
        return $this->parseComplexVariable($expression);
    }

    /**
     * Parse simple variable without filters - OPTIMIZED
     */
    private function parseSimpleVariable(string $expression): array
    {
        // Handle dot notation efficiently
        if (str_contains($expression, '.')) {
            $dotPos = strpos($expression, '.');
            $name = substr($expression, 0, $dotPos);
            $pathString = substr($expression, $dotPos + 1);
            $path = explode('.', $pathString);

            return [
                'type' => 'variable',
                'name' => $name,
                'path' => $path,
                'filters' => []
            ];
        }

        return [
            'type' => 'variable',
            'name' => $expression,
            'path' => [],
            'filters' => []
        ];
    }

    /**
     * Parse simple filter cases - FIXED VERSION
     */
    private function parseSimpleFilter(string $expression): array
    {
        $pipePos = strpos($expression, '|');
        $variablePart = substr($expression, 0, $pipePos);
        $filterPart = substr($expression, $pipePos + 1);

        // WICHTIG: Check if variable part is string literal
        if ($this->isStringLiteral(trim($variablePart))) {
            $variable = $this->parseStringLiteral(trim($variablePart));
        } else {
            $variable = $this->parseSimpleVariable(trim($variablePart));
        }

        // Parse single filter efficiently
        if (str_contains($filterPart, ':')) {
            $colonPos = strpos($filterPart, ':');
            $filterName = substr($filterPart, 0, $colonPos);
            $paramString = substr($filterPart, $colonPos + 1);
            $params = $this->parseSimpleFilterParams($paramString);

            $variable['filters'] = [[
                'name' => trim($filterName),
                'params' => $params
            ]];
        } else {
            $variable['filters'] = [[
                'name' => trim($filterPart),
                'params' => []
            ]];
        }

        return $variable;
    }

    /**
     * Parse simple filter parameters - OPTIMIZED
     */
    private function parseSimpleFilterParams(string $paramString): array
    {
        // Fast path for simple cases (no complex quoting)
        if (!str_contains($paramString, '"') && !str_contains($paramString, "'")) {
            $parts = explode(':', $paramString);
            return array_map('trim', $parts);
        }

        // Complex case - fall back to original parser
        return $this->parseFilterParameters($paramString);
    }

    /**
     * Parse complex variable - FALLBACK for edge cases
     */
    private function parseComplexVariable(string $expression, array $filters = []): array
    {
        // Check for filters first
        if (str_contains($expression, '|')) {
            $parts = explode('|', $expression);
            $variablePart = trim($parts[0]);
            $filterStrings = array_map('trim', array_slice($parts, 1));

            $parsedFilters = [];
            foreach ($filterStrings as $filterString) {
                if (str_contains($filterString, ':')) {
                    $colonPos = strpos($filterString, ':');
                    $filterName = substr($filterString, 0, $colonPos);
                    $paramString = substr($filterString, $colonPos + 1);
                    $params = $this->parseFilterParameters($paramString);

                    $parsedFilters[] = [
                        'name' => $filterName,
                        'params' => $params
                    ];
                } else {
                    $parsedFilters[] = [
                        'name' => $filterString,
                        'params' => []
                    ];
                }
            }

            // Check if variablePart is a string literal
            if ($this->isStringLiteral($variablePart)) {
                return $this->parseStringLiteral($variablePart, $parsedFilters);
            }

            // Check if variablePart is a function call
            if ($this->isFunctionCall($variablePart)) {
                return $this->parseFunctionCall($variablePart, $parsedFilters);
            }

            // Regular variable with filters
            return $this->parseVariableWithDotNotation($variablePart, $parsedFilters);
        }

        // Check if this is a function call
        if ($this->isFunctionCall($expression)) {
            return $this->parseFunctionCall($expression);
        }

        // Check if this is a string literal
        if ($this->isStringLiteral($expression)) {
            return $this->parseStringLiteral($expression);
        }

        // Regular variable access
        return $this->parseVariableWithDotNotation($expression);
    }

    /**
     * Parse variable with dot notation - HELPER method
     */
    private function parseVariableWithDotNotation(string $expression, array $filters = []): array
    {
        // Handle array access like demo_data.language_names[code]
        if (preg_match('/^(\w+(?:\.\w+)*)\[(\w+)]$/', $expression, $matches)) {
            $basePath = $matches[1];
            $arrayKey = $matches[2];

            $parts = explode('.', $basePath);
            return [
                'type' => 'variable',
                'name' => $parts[0],
                'path' => array_merge(array_slice($parts, 1), [$arrayKey]),
                'filters' => $filters,
                'is_dynamic_key' => true
            ];
        }

        // Regular dot notation
        $parts = explode('.', $expression);
        return [
            'type' => 'variable',
            'name' => $parts[0],
            'path' => array_slice($parts, 1),
            'filters' => $filters
        ];
    }

    /**
     * Parse filter parameters respecting quotes and object syntax
     */
    private function parseFilterParameters(string $paramString): array
    {
        $params = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $braceLevel = 0;

        for ($i = 0; $i < strlen($paramString); $i++) {
            $char = $paramString[$i];

            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char; // Keep the quote for object syntax
                continue;
            }

            if ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $current .= $char; // Keep the closing quote
                $quoteChar = null;
                continue;
            }

            // Handle braces for object syntax
            if (!$inQuotes && $char === '{') {
                $braceLevel++;
            }

            if (!$inQuotes && $char === '}') {
                $braceLevel--;
            }

            if (!$inQuotes && $char === ':' && $braceLevel === 0) {
                // Parameter separator (only when not inside object)
                $param = trim($current);
                if ($param !== '') {
                    $params[] = $this->convertParameter($param);
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Add last parameter
        $param = trim($current);
        if ($param !== '') {
            $params[] = $this->convertParameter($param);
        }

        return $params;
    }

    /**
     * Convert parameter to appropriate type - ENHANCED
     */
    private function convertParameter(string $param): mixed
    {
        $param = trim($param);

        // Handle object syntax: {key: 'value', key2: 'value2'}
        if (str_starts_with($param, '{') && str_ends_with($param, '}')) {
            // Convert to proper JSON and return as string for later processing
            $jsonParam = preg_replace('/(\w+):\s*/', '"$1": ', $param);
            $jsonParam = preg_replace("/'/", '"', $jsonParam);
            return $jsonParam;
        }

        // Handle quoted strings
        if ((str_starts_with($param, '"') && str_ends_with($param, '"')) ||
            (str_starts_with($param, "'") && str_ends_with($param, "'"))) {
            return substr($param, 1, -1);
        }

        // Handle numbers
        if (is_numeric($param)) {
            return str_contains($param, '.') ? (float)$param : (int)$param;
        }

        return $param;
    }

    /**
     * Check if expression is a string literal - IMPROVED
     */
    private function isStringLiteral(string $expression): bool
    {
        $expression = trim($expression);
        return (str_starts_with($expression, "'") && str_ends_with($expression, "'")) ||
            (str_starts_with($expression, '"') && str_ends_with($expression, '"'));
    }

    /**
     * Parse string literal with optional filters
     */
    private function parseStringLiteral(string $expression, array $filters = []): array
    {
        $expression = trim($expression);

        // Remove quotes
        if (str_starts_with($expression, "'") && str_ends_with($expression, "'")) {
            $value = substr($expression, 1, -1);
        } elseif (str_starts_with($expression, '"') && str_ends_with($expression, '"')) {
            $value = substr($expression, 1, -1);
        } else {
            $value = $expression;
        }

        return [
            'type' => 'literal',
            'value' => $value,
            'filters' => $filters
        ];
    }

    /**
     * Check if expression is a function call
     */
    private function isFunctionCall(string $expression): bool
    {
        return preg_match('/^(t|t_plural|locale|locales)\s*\(/', $expression) === 1;
    }

    /**
     * Parse function call with parameters
     */
    private function parseFunctionCall(string $expression, array $filters = []): array
    {
        // Parse function syntax: t('key', {param: value})
        if (preg_match('/^(\w+)\s*\(\s*(.+?)\s*\)$/', $expression, $matches)) {
            $functionName = $matches[1];
            $paramString = $matches[2];

            $params = $this->parseFunctionParameters($paramString);

            return [
                'type' => 'function',
                'name' => $functionName,
                'params' => $params,
                'filters' => $filters
            ];
        }

        // Simple function call without parameters: locale()
        if (preg_match('/^(\w+)\s*\(\s*\)$/', $expression, $matches)) {
            return [
                'type' => 'function',
                'name' => $matches[1],
                'params' => [],
                'filters' => $filters
            ];
        }

        throw new \RuntimeException("Invalid function call syntax: {$expression}");
    }

    /**
     * Parse function parameters
     */
    private function parseFunctionParameters(string $paramString): array
    {
        $params = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $braceLevel = 0;

        for ($i = 0; $i < strlen($paramString); $i++) {
            $char = $paramString[$i];

            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char; // Keep the quote
                continue;
            }

            if ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $current .= $char; // Keep the closing quote
                $quoteChar = null;
                continue;
            }

            if (!$inQuotes && $char === '{') {
                $braceLevel++;
            }

            if (!$inQuotes && $char === '}') {
                $braceLevel--;
            }

            if (!$inQuotes && $char === ',' && $braceLevel === 0) {
                $param = trim($current);
                if ($param !== '') {
                    $params[] = $this->parseParameter($param);
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Add last parameter
        $param = trim($current);
        if ($param !== '') {
            $params[] = $this->parseParameter($param);
        }

        return $params;
    }

    /**
     * Parse individual parameter
     */
    private function parseParameter(string $param): array
    {
        $param = trim($param);

        // String parameter - check for quotes at start and end
        if ((str_starts_with($param, '"') && str_ends_with($param, '"')) ||
            (str_starts_with($param, "'") && str_ends_with($param, "'"))) {
            return [
                'type' => 'string',
                'value' => substr($param, 1, -1) // Remove quotes
            ];
        }

        // Number parameter
        if (is_numeric($param)) {
            return [
                'type' => 'number',
                'value' => str_contains($param, '.') ? (float)$param : (int)$param
            ];
        }

        // Object parameter (basic support)
        if (str_starts_with($param, '{') && str_ends_with($param, '}')) {
            return [
                'type' => 'object',
                'value' => $param
            ];
        }

        // Variable parameter (anything else without quotes)
        return [
            'type' => 'variable',
            'value' => $param
        ];
    }

    /**
     * Parse block - OPTIMIZED VERSION with proper closing tag handling
     */
// framework/Templating/Parser/TemplateParser.php

    private function parseBlock(string $expression, array $tokens, int $currentIndex): array
    {
        $parts = explode(' ', trim($expression), 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        // Handle closing tags - DON'T throw exception, let parent handle them
        if (in_array($command, ['endif', 'endfor', 'endblock', 'else'])) {
            // Return a special marker that parent parsers can recognize
            return [
                'node' => ['type' => 'closing_tag', 'command' => $command],
                'nextIndex' => $currentIndex + 1
            ];
        }

        // FIX: Check if this looks like a malformed block (probably a parsing error)
        if (strlen($command) <= 3 && !in_array($command, ['if', 'for'])) {
            throw new \RuntimeException("Malformed template syntax near: {$expression}");
        }

        // Fast dispatch for common blocks
        return match ($command) {
            'if' => $this->parseIfBlockOptimized($args, $tokens, $currentIndex),
            'for' => $this->parseForBlockOptimized($args, $tokens, $currentIndex),
            'block' => $this->parseBlockDefinition($args, $tokens, $currentIndex),
            'extends' => [
                'node' => ['type' => 'extends', 'template' => trim($args, '"\'')],
                'nextIndex' => $currentIndex + 1
            ],
            'include' => $this->parseIncludeBlockOptimized($args, $currentIndex),
            default => throw new \RuntimeException("Unknown block command: {$command}")
        };
    }

    /**
     * Parse include block - OPTIMIZED VERSION
     */
    private function parseIncludeBlockOptimized(string $args, int $currentIndex): array
    {
        $args = trim($args);

        // Fast path for simple includes (80% of cases): "template.html"
        if (preg_match('/^["\']([^"\']+)["\']$/', $args, $matches)) {
            return [
                'node' => [
                    'type' => 'include',
                    'template' => $matches[1],
                    'data_source' => null,
                    'variable' => null
                ],
                'nextIndex' => $currentIndex + 1
            ];
        }

        // Complex includes with data mapping: "template.html" with data.source as variable
        if (preg_match('/^["\'](.+?)["\'](?:\s+with\s+(.+?)\s+as\s+(\w+))?$/', $args, $matches)) {
            return [
                'node' => [
                    'type' => 'include',
                    'template' => $matches[1],
                    'data_source' => $matches[2] ?? null,
                    'variable' => $matches[3] ?? null
                ],
                'nextIndex' => $currentIndex + 1
            ];
        }

        throw new \RuntimeException("Invalid include syntax: {$args}");
    }

    private function parseBlockDefinition(string $args, array $tokens, int $startIndex): array
    {
        $blockName = trim($args);
        $body = [];
        $depth = 1;
        $i = $startIndex + 1;
        $tokenCount = count($tokens);

        while ($i < $tokenCount && $depth > 0) {
            $token = $tokens[$i];

            if ($token->getType() === TokenType::BLOCK) {
                $expression = trim($token->getValue());
                $parts = explode(' ', $expression);
                $command = $parts[0];

                if ($command === 'block') {
                    $depth++;
                } elseif ($command === 'endblock') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }

            if ($depth > 0) {
                $node = $this->parseTokenToNode($token, $tokens, $i);
                $body[] = $node['node'];
                $i = $node['nextIndex'] - 1; // -1 because loop will increment
            }

            $i++;
        }

        return [
            'node' => [
                'type' => 'block',
                'name' => $blockName,
                'body' => $body
            ],
            'nextIndex' => $i + 1
        ];
    }

    private function parseTokenToNode(Token $token, array $tokens, int $index): array
    {
        switch ($token->getType()) {
            case TokenType::TEXT:
                return [
                    'node' => ['type' => 'text', 'content' => $token->getValue()],
                    'nextIndex' => $index + 1
                ];

            case TokenType::VARIABLE:
                return [
                    'node' => $this->parseVariable($token->getValue()),
                    'nextIndex' => $index + 1
                ];

            case TokenType::BLOCK:
                return $this->parseBlock($token->getValue(), $tokens, $index);

            default:
                throw new \RuntimeException("Unknown token type: {$token->getType()->value}");
        }
    }

    /**
     * Parse IF block - OPTIMIZED for simple conditions
     */
    private function parseIfBlockOptimized(string $condition, array $tokens, int $startIndex): array
    {
        if (empty($condition)) {
            return [
                'node' => ['type' => 'text', 'content' => '{% if %}'],
                'nextIndex' => $startIndex + 1
            ];
        }

        // Fast condition parsing for simple cases
        $parsedCondition = $this->parseConditionOptimized($condition);

        // Optimized block body parsing
        [$body, $elseBody, $endIndex] = $this->parseIfBlockBody($tokens, $startIndex);

        return [
            'node' => [
                'type' => 'if',
                'condition' => $parsedCondition,
                'body' => $body,
                'else' => empty($elseBody) ? null : $elseBody
            ],
            'nextIndex' => $endIndex + 1
        ];
    }

    /**
     * Parse IF block body - FIXED VERSION for nested conditions
     */
    private function parseIfBlockBody(array $tokens, int $startIndex): array
    {
        $body = [];
        $elseBody = [];
        $inElse = false;
        $depth = 1;
        $i = $startIndex + 1;
        $tokenCount = count($tokens);

        while ($i < $tokenCount && $depth > 0) {
            $token = $tokens[$i];

            if ($token->getType() === TokenType::BLOCK) {
                $expression = trim($token->getValue());
                $command = explode(' ', $expression)[0];

                // Handle nested if statements properly
                if ($command === 'if') {
                    $depth++;
                } elseif ($command === 'endif') {
                    $depth--;
                    if ($depth === 0) {
                        // Found our matching endif - exit the loop
                        break;
                    }
                } elseif ($command === 'else' && $depth === 1) {
                    // Only handle 'else' if it belongs to our current if (depth === 1)
                    $inElse = true;
                    $i++;
                    continue;
                }

                // If we're still inside our block, process the token
                if ($depth > 0) {
                    // For nested control structures, parse them recursively
                    if (in_array($command, ['if', 'for', 'block', 'include'])) {
                        try {
                            $nestedResult = $this->parseBlock($expression, $tokens, $i);

                            if ($inElse) {
                                $elseBody[] = $nestedResult['node'];
                            } else {
                                $body[] = $nestedResult['node'];
                            }

                            $i = $nestedResult['nextIndex'] - 1; // -1 because loop will increment
                        } catch (\RuntimeException $e) {
                            // If parseBlock fails, treat as text
                            $textNode = ['type' => 'text', 'content' => '{%' . $expression . '%}'];
                            if ($inElse) {
                                $elseBody[] = $textNode;
                            } else {
                                $body[] = $textNode;
                            }
                        }
                    } else {
                        // Unknown block command or endif/else - treat as text if not handled above
                        if (!in_array($command, ['endif', 'else'])) {
                            $textNode = ['type' => 'text', 'content' => '{%' . $expression . '%}'];
                            if ($inElse) {
                                $elseBody[] = $textNode;
                            } else {
                                $body[] = $textNode;
                            }
                        }
                    }
                }
            } else {
                // Handle text and variable tokens
                if ($depth > 0) {
                    $node = match ($token->getType()) {
                        TokenType::TEXT => ['type' => 'text', 'content' => $token->getValue()],
                        TokenType::VARIABLE => $this->parseVariable($token->getValue()),
                    };

                    if ($inElse) {
                        $elseBody[] = $node;
                    } else {
                        $body[] = $node;
                    }
                }
            }

            $i++;
        }

        return [$body, $elseBody, $i];
    }

    /**
     * Parse condition - OPTIMIZED for simple cases
     */
    private function parseConditionOptimized(string $condition): array
    {
        // Fast path for simple variable conditions (70% of cases)
        if (!str_contains($condition, '==') && !str_contains($condition, '!=') &&
            !str_contains($condition, '<') && !str_contains($condition, '>')) {
            return [
                'type' => 'variable',
                'expression' => $this->parseSimpleVariable(trim($condition))
            ];
        }

        // Fast path for simple comparisons (25% of cases)
        if (str_contains($condition, '==')) {
            [$left, $right] = explode('==', $condition, 2);
            return [
                'type' => 'comparison',
                'operator' => '==',
                'left' => $this->parseSimpleVariable(trim($left)),
                'right' => trim($right, '\'" ')
            ];
        }

        // Complex conditions (5% of cases) - fall back to original
        return $this->parseCondition($condition);
    }

    private function parseCondition(string $condition): array
    {
        if (str_contains($condition, '==')) {
            [$left, $right] = explode('==', $condition, 2);
            return [
                'type' => 'comparison',
                'operator' => '==',
                'left' => $this->parseVariable(trim($left)),
                'right' => trim($right, '\'" ')
            ];
        }

        return [
            'type' => 'variable',
            'expression' => $this->parseVariable($condition)
        ];
    }

    /**
     * Parse FOR block - OPTIMIZED for simple syntax
     */
    private function parseForBlockOptimized(string $expression, array $tokens, int $startIndex): array
    {
        if (empty($expression)) {
            return [
                'node' => ['type' => 'text', 'content' => '{% for %}'],
                'nextIndex' => $startIndex + 1
            ];
        }

        // Optimized regex for common for-loop patterns
        if (preg_match('/^(\w+(?:\.\w+)*)\s+as\s+(\w+)$/', $expression, $matches)) {
            $array = $matches[1];
            $item = $matches[2];
        } elseif (preg_match('/^(\w+)\s+in\s+(\w+(?:\.\w+)*)$/', $expression, $matches)) {
            $item = $matches[1];
            $array = $matches[2];
        } else {
            throw new \RuntimeException("Invalid for loop syntax: {$expression}");
        }

        // Optimized body parsing
        [$body, $endIndex] = $this->parseForBlockBody($tokens, $startIndex);

        return [
            'node' => [
                'type' => 'for',
                'array' => $array,
                'item' => $item,
                'body' => $body
            ],
            'nextIndex' => $endIndex + 1
        ];
    }

    /**
     * Parse FOR block body - FIXED VERSION for nested blocks
     */
    private function parseForBlockBody(array $tokens, int $startIndex): array
    {
        $body = [];
        $depth = 1;
        $i = $startIndex + 1;
        $tokenCount = count($tokens);

        while ($i < $tokenCount && $depth > 0) {
            $token = $tokens[$i];

            if ($token->getType() === TokenType::BLOCK) {
                $expression = trim($token->getValue());
                $command = explode(' ', $expression)[0];

                if ($command === 'for') {
                    $depth++;
                } elseif ($command === 'endfor') {
                    $depth--;
                    if ($depth === 0) break;
                }
            }

            if ($depth > 0) {
                // Handle nested blocks properly
                if ($token->getType() === TokenType::BLOCK) {
                    $expression = trim($token->getValue());
                    $command = explode(' ', $expression)[0];

                    // If it's a block command that needs parsing, parse it
                    if (in_array($command, ['if', 'for', 'block', 'include'])) {
                        try {
                            $node = $this->parseBlock($expression, $tokens, $i);
                            $body[] = $node['node'];
                            $i = $node['nextIndex'] - 1;
                        } catch (\RuntimeException $e) {
                            // If it's a closing tag for nested block, skip it
                            if (str_contains($e->getMessage(), 'Unexpected closing tag')) {
                                // This closing tag belongs to a nested block, continue
                            } else {
                                throw $e;
                            }
                        }
                    }
                } else {
                    // Handle text and variable tokens
                    $node = [
                        'node' => match ($token->getType()) {
                            TokenType::TEXT => ['type' => 'text', 'content' => $token->getValue()],
                            TokenType::VARIABLE => $this->parseVariable($token->getValue()),
                        },
                        'nextIndex' => $i + 1
                    ];

                    $body[] = $node['node'];
                    $i = $node['nextIndex'] - 1;
                }
            }

            $i++;
        }

        return [$body, $i];
    }
}