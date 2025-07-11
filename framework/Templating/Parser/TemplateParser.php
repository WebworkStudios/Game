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

    private function tokenize(string $content): array
    {
        $tokens = [];
        $position = 0;
        $length = strlen($content);

        while ($position < $length) {
            $variablePos = strpos($content, self::VARIABLE_START, $position);
            $blockPos = strpos($content, self::BLOCK_START, $position);

            // Find next delimiter
            $nextPos = false;
            $type = null;

            if ($variablePos !== false && ($blockPos === false || $variablePos < $blockPos)) {
                $nextPos = $variablePos;
                $type = TokenType::VARIABLE;
            } elseif ($blockPos !== false) {
                $nextPos = $blockPos;
                $type = TokenType::BLOCK;
            }

            // Add text before delimiter
            if ($nextPos === false) {
                if ($position < $length) {
                    $tokens[] = new Token(
                        TokenType::TEXT,
                        substr($content, $position),
                        $position
                    );
                }
                break;
            }

            if ($nextPos > $position) {
                $tokens[] = new Token(
                    TokenType::TEXT,
                    substr($content, $position, $nextPos - $position),
                    $position
                );
            }

            // Parse token
            if ($type === TokenType::VARIABLE) {
                $end = strpos($content, self::VARIABLE_END, $nextPos + 2);
                if ($end === false) {
                    throw new \RuntimeException("Unterminated variable at position {$nextPos}");
                }
                $expression = $this->cleanExpression(substr($content, $nextPos + 2, $end - $nextPos - 2));
                $tokens[] = new Token(TokenType::VARIABLE, $expression, $nextPos, $end + 2);
                $position = $end + 2;
            } else {
                $end = strpos($content, self::BLOCK_END, $nextPos + 2);
                if ($end === false) {
                    throw new \RuntimeException("Unterminated block at position {$nextPos}");
                }
                $expression = $this->cleanExpression(substr($content, $nextPos + 2, $end - $nextPos - 2));
                $tokens[] = new Token(TokenType::BLOCK, $expression, $nextPos, $end + 2);
                $position = $end + 2;
            }
        }

        return $tokens;
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
                    $ast[] = $blockResult['node'];
                    $i = $blockResult['nextIndex'] - 1; // -1 because loop will increment
                    break;
            }
        }

        return $ast;
    }

    private function parseVariable(string $expression): array
    {
        // Check for filters first
        if (str_contains($expression, '|')) {
            $parts = explode('|', $expression);
            $variablePart = trim($parts[0]);
            $filterStrings = array_map('trim', array_slice($parts, 1));

            $filters = [];
            foreach ($filterStrings as $filterString) {
                if (str_contains($filterString, ':')) {
                    $colonPos = strpos($filterString, ':');
                    $filterName = substr($filterString, 0, $colonPos);
                    $paramString = substr($filterString, $colonPos + 1);
                    $params = $this->parseFilterParameters($paramString);

                    $filters[] = [
                        'name' => $filterName,
                        'params' => $params
                    ];
                } else {
                    $filters[] = [
                        'name' => $filterString,
                        'params' => []
                    ];
                }
            }

            // Check if variablePart is a function call
            if ($this->isFunctionCall($variablePart)) {
                return $this->parseFunctionCall($variablePart, $filters);
            }

            $variableParts = explode('.', $variablePart);
            return [
                'type' => 'variable',
                'name' => $variableParts[0],
                'path' => array_slice($variableParts, 1),
                'filters' => $filters
            ];
        }

        // Check if this is a function call
        if ($this->isFunctionCall($expression)) {
            return $this->parseFunctionCall($expression);
        }

        // Regular variable access
        $parts = explode('.', $expression);
        return [
            'type' => 'variable',
            'name' => $parts[0],
            'path' => array_slice($parts, 1),
            'filters' => []
        ];
    }

    /**
     * Parse filter parameters respecting quotes
     */
    private function parseFilterParameters(string $paramString): array
    {
        $params = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;

        for ($i = 0; $i < strlen($paramString); $i++) {
            $char = $paramString[$i];

            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                continue; // Skip the opening quote
            }

            if ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = null;
                continue; // Skip the closing quote
            }

            if (!$inQuotes && $char === ':') {
                // Parameter separator
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
     * Convert parameter to appropriate type
     */
    private function convertParameter(string $param): mixed
    {
        if (is_numeric($param)) {
            return str_contains($param, '.') ? (float)$param : (int)$param;
        }

        return $param;
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

    private function parseBlock(string $expression, array $tokens, int $currentIndex): array
    {
        $parts = explode(' ', trim($expression), 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        // Handle closing tags - these should not create nodes
        if (in_array($command, ['endif', 'endfor', 'endblock', 'else'])) {
            throw new \RuntimeException("Unexpected closing tag: {$command}");
        }

        switch ($command) {
            case 'extends':
                return [
                    'node' => [
                        'type' => 'extends',
                        'template' => trim($args, '"\'')
                    ],
                    'nextIndex' => $currentIndex + 1
                ];

            case 'block':
                return $this->parseBlockDefinition($args, $tokens, $currentIndex);

            case 'if':
                // Wenn if ohne Argumente, als Text behandeln
                if (empty($args)) {
                    return [
                        'node' => ['type' => 'text', 'content' => '{% ' . $expression . ' %}'],
                        'nextIndex' => $currentIndex + 1
                    ];
                }
                return $this->parseIfBlock($args, $tokens, $currentIndex);

            case 'for':
                // Wenn for ohne Argumente, als Text behandeln
                if (empty($args)) {
                    return [
                        'node' => ['type' => 'text', 'content' => '{% ' . $expression . ' %}'],
                        'nextIndex' => $currentIndex + 1
                    ];
                }
                return $this->parseForBlock($args, $tokens, $currentIndex);

            case 'include':
                return $this->parseIncludeBlock($args, $tokens, $currentIndex);

            default:
                throw new \RuntimeException("Unknown block command: {$command}");
        }
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

    private function parseIfBlock(string $condition, array $tokens, int $startIndex): array
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
                $parts = explode(' ', $expression);
                $command = $parts[0];

                if ($command === 'if') {
                    $depth++;
                } elseif ($command === 'endif') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                } elseif ($command === 'else' && $depth === 1) {
                    $inElse = true;
                    $i++;
                    continue;
                }
            }

            // Only parse non-closing tokens at our depth level
            if ($depth > 0) {
                // For nested blocks, let parseTokenToNode handle them
                if ($token->getType() === TokenType::BLOCK) {
                    $expression = trim($token->getValue());
                    $command = explode(' ', $expression)[0];

                    // If it's a nested block command, parse it normally
                    if (in_array($command, ['if', 'for', 'block', 'include'])) {
                        $node = $this->parseTokenToNode($token, $tokens, $i);
                        if ($inElse) {
                            $elseBody[] = $node['node'];
                        } else {
                            $body[] = $node['node'];
                        }
                        $i = $node['nextIndex'] - 1; // -1 because loop will increment
                    }
                    // Note: Closing tags that belong to nested blocks are intentionally skipped
                    // They are handled by their respective block parsers
                } else {
                    // Handle text and variable tokens normally
                    $node = $this->parseTokenToNode($token, $tokens, $i);
                    if ($inElse) {
                        $elseBody[] = $node['node'];
                    } else {
                        $body[] = $node['node'];
                    }
                    $i = $node['nextIndex'] - 1; // -1 because loop will increment
                }
            }

            $i++;
        }

        return [
            'node' => [
                'type' => 'if',
                'condition' => $this->parseCondition($condition),
                'body' => $body,
                'else' => empty($elseBody) ? null : $elseBody
            ],
            'nextIndex' => $i + 1
        ];
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

    private function parseForBlock(string $expression, array $tokens, int $startIndex): array
    {
        // Support both "item in array" and "array as item" syntax with dot notation
        if (preg_match('/(\w+)\s+in\s+(.+)/', $expression, $matches)) {
            $item = $matches[1];
            $array = trim($matches[2]);
        } elseif (preg_match('/([a-zA-Z_][a-zA-Z0-9_.]*)\s+as\s+(\w+)/', $expression, $matches)) {
            $array = trim($matches[1]);
            $item = $matches[2];
        } else {
            throw new \RuntimeException("Invalid for loop syntax: {$expression}");
        }

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

                if ($command === 'for') {
                    $depth++;
                } elseif ($command === 'endfor') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }

            if ($depth > 0) {
                // For nested blocks, let parseTokenToNode handle them
                if ($token->getType() === TokenType::BLOCK) {
                    $expression = trim($token->getValue());
                    $command = explode(' ', $expression)[0];

                    // If it's a nested block command, parse it normally
                    if (in_array($command, ['if', 'for', 'block', 'include'])) {
                        $node = $this->parseTokenToNode($token, $tokens, $i);
                        $body[] = $node['node'];
                        $i = $node['nextIndex'] - 1; // -1 because loop will increment
                    }
                    // Note: Closing tags that belong to nested blocks are intentionally skipped
                    // They are handled by their respective block parsers
                } else {
                    // Handle text and variable tokens normally
                    $node = $this->parseTokenToNode($token, $tokens, $i);
                    $body[] = $node['node'];
                    $i = $node['nextIndex'] - 1; // -1 because loop will increment
                }
            }

            $i++;
        }

        return [
            'node' => [
                'type' => 'for',
                'array' => $array,
                'item' => $item,
                'body' => $body
            ],
            'nextIndex' => $i + 1
        ];
    }

    private function parseIncludeBlock(string $args, array $tokens, int $currentIndex): array
    {
        // Parse: "template.html" with data.source as variable
        if (preg_match('/^["\'](.+?)["\'](?:\s+with\s+(.+?)\s+as\s+(\w+))?$/', trim($args), $matches)) {
            $template = $matches[1];
            $dataSource = $matches[2] ?? null;
            $variable = $matches[3] ?? null;

            $node = [
                'type' => 'include',
                'template' => $template,
                'data_source' => $dataSource,
                'variable' => $variable
            ];

            return [
                'node' => $node,
                'nextIndex' => $currentIndex + 1
            ];
        }

        throw new \RuntimeException("Invalid include syntax: {$args}");
    }
}