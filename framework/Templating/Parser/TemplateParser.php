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
        // Check for filters: variable|filter|filter2
        if (str_contains($expression, '|')) {
            $parts = explode('|', $expression);
            $variablePart = trim($parts[0]);
            $filters = array_map('trim', array_slice($parts, 1));

            $variableParts = explode('.', $variablePart);

            return [
                'type' => 'variable',
                'name' => $variableParts[0],
                'path' => array_slice($variableParts, 1),
                'filters' => $filters
            ];
        }

        // No filters - original logic
        $parts = explode('.', $expression);
        return [
            'type' => 'variable',
            'name' => $parts[0],
            'path' => array_slice($parts, 1)
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
                    } else {
                        // Skip closing tags that belong to nested blocks
                        // They will be handled by their respective block parsers
                    }
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
                    // Skip closing tags that belong to nested blocks
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