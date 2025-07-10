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
                $expression = trim(substr($content, $nextPos + 2, $end - $nextPos - 2));
                $tokens[] = new Token(TokenType::VARIABLE, $expression, $nextPos, $end + 2);
                $position = $end + 2;
            } else {
                $end = strpos($content, self::BLOCK_END, $nextPos + 2);
                if ($end === false) {
                    throw new \RuntimeException("Unterminated block at position {$nextPos}");
                }
                $expression = trim(substr($content, $nextPos + 2, $end - $nextPos - 2));
                $tokens[] = new Token(TokenType::BLOCK, $expression, $nextPos, $end + 2);
                $position = $end + 2;
            }
        }

        return $tokens;
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

        // Handle closing tags - these should be handled by their parent blocks
        if (in_array($command, ['endif', 'endfor', 'endblock'])) {
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
                return $this->parseIfBlock($args, $tokens, $currentIndex);

            case 'for':
                return $this->parseForBlock($args, $tokens, $currentIndex);

            case 'include':
                return [
                    'node' => [
                        'type' => 'include',
                        'template' => trim($args, '"\'')
                    ],
                    'nextIndex' => $currentIndex + 1
                ];

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

            if ($depth > 0) {
                $node = $this->parseTokenToNode($token, $tokens, $i);
                if ($inElse) {
                    $elseBody[] = $node['node'];
                } else {
                    $body[] = $node['node'];
                }
                $i = $node['nextIndex'] - 1; // -1 because loop will increment
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

    private function parseForBlock(string $expression, array $tokens, int $startIndex): array
    {
        if (!preg_match('/(\w+)\s+as\s+(\w+)/', $expression, $matches)) {
            throw new \RuntimeException("Invalid for loop syntax: {$expression}");
        }

        $array = $matches[1];
        $item = $matches[2];
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
                $node = $this->parseTokenToNode($token, $tokens, $i);
                $body[] = $node['node'];
                $i = $node['nextIndex'] - 1; // -1 because loop will increment
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
}