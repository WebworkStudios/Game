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
            // Find next delimiter
            $variablePos = strpos($content, self::VARIABLE_START, $position);
            $blockPos = strpos($content, self::BLOCK_START, $position);

            // Determine which comes first
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
                // No more delimiters, add remaining text
                if ($position < $length) {
                    $tokens[] = new Token(
                        TokenType::TEXT,
                        substr($content, $position),
                        $position
                    );
                }
                break;
            }

            // Add text token if there's content before delimiter
            if ($nextPos > $position) {
                $tokens[] = new Token(
                    TokenType::TEXT,
                    substr($content, $position, $nextPos - $position),
                    $position
                );
            }

            // Parse variable or block
            if ($type === TokenType::VARIABLE) {
                $token = $this->parseVariable($content, $nextPos);
            } else {
                $token = $this->parseBlock($content, $nextPos);
            }

            $tokens[] = $token;
            $position = $token->getEndPosition();
        }

        return $tokens;
    }

    private function parseVariable(string $content, int $start): Token
    {
        $end = strpos($content, self::VARIABLE_END, $start + 2);
        if ($end === false) {
            throw new \RuntimeException("Unterminated variable at position {$start}");
        }

        $expression = trim(substr($content, $start + 2, $end - $start - 2));

        return new Token(
            TokenType::VARIABLE,
            $expression,
            $start,
            $end + 2
        );
    }

    private function parseBlock(string $content, int $start): Token
    {
        $end = strpos($content, self::BLOCK_END, $start + 2);
        if ($end === false) {
            throw new \RuntimeException("Unterminated block at position {$start}");
        }

        $expression = trim(substr($content, $start + 2, $end - $start - 2));

        return new Token(
            TokenType::BLOCK,
            $expression,
            $start,
            $end + 2
        );
    }

    private function parseTokens(array $tokens): array
    {
        $ast = [];
        $i = 0;

        while ($i < count($tokens)) {
            $token = $tokens[$i];

            if ($token->getType() === TokenType::TEXT) {
                $ast[] = ['type' => 'text', 'content' => $token->getValue()];
            } elseif ($token->getType() === TokenType::VARIABLE) {
                $ast[] = $this->parseVariableExpression($token->getValue());
            } elseif ($token->getType() === TokenType::BLOCK) {
                $result = $this->parseBlockExpression($token->getValue(), $tokens, $i);
                $ast[] = $result['node'];
                $i = $result['position']; // Skip processed tokens
                continue;
            }

            $i++;
        }

        return $ast;
    }

    private function parseVariableExpression(string $expression): array
    {
        // Handle dot notation: player.name -> ['player', 'name']
        $parts = explode('.', $expression);

        return [
            'type' => 'variable',
            'name' => $parts[0],
            'path' => array_slice($parts, 1)
        ];
    }

    private function parseBlockExpression(string $expression, array $tokens, int &$position): array
    {
        $parts = explode(' ', $expression, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';

        switch ($command) {
            case 'if':
                return $this->parseIfBlock($args, $tokens, $position);

            case 'for':
                return $this->parseForBlock($args, $tokens, $position);

            case 'include':
                return [
                    'node' => [
                        'type' => 'include',
                        'template' => trim($args, '"\'')
                    ],
                    'position' => $position
                ];

            default:
                throw new \RuntimeException("Unknown block command: {$command}");
        }
    }

    private function parseIfBlock(string $condition, array $tokens, int &$position): array
    {
        $body = $this->collectBlockBody($tokens, $position, 'if');

        return [
            'node' => [
                'type' => 'if',
                'condition' => $this->parseCondition($condition),
                'body' => $body['body'],
                'else' => $body['else'] ?? null
            ],
            'position' => $body['endPosition']
        ];
    }

    private function parseForBlock(string $expression, array $tokens, int &$position): array
    {
        // Parse: "players as player" or "matches as match"
        if (!preg_match('/(\w+)\s+as\s+(\w+)/', $expression, $matches)) {
            throw new \RuntimeException("Invalid for loop syntax: {$expression}");
        }

        $array = $matches[1];
        $item = $matches[2];

        $body = $this->collectBlockBody($tokens, $position, 'for');

        return [
            'node' => [
                'type' => 'for',
                'array' => $array,
                'item' => $item,
                'body' => $body['body']
            ],
            'position' => $body['endPosition']
        ];
    }

    private function collectBlockBody(array $tokens, int &$position, string $blockType): array
    {
        $body = [];
        $else = null;
        $depth = 1;
        $i = $position + 1;

        while ($i < count($tokens) && $depth > 0) {
            $token = $tokens[$i];

            if ($token->getType() === TokenType::BLOCK) {
                $expression = $token->getValue();
                $parts = explode(' ', $expression);
                $command = $parts[0];

                if ($command === $blockType) {
                    $depth++;
                } elseif ($command === "end{$blockType}") {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                } elseif ($command === 'else' && $depth === 1 && $blockType === 'if') {
                    // Start collecting else body
                    $else = [];
                    $i++;
                    continue;
                }
            }

            if ($depth > 0) {
                if ($else !== null) {
                    if ($token->getType() === TokenType::TEXT) {
                        $else[] = ['type' => 'text', 'content' => $token->getValue()];
                    } elseif ($token->getType() === TokenType::VARIABLE) {
                        $else[] = $this->parseVariableExpression($token->getValue());
                    }
                } else {
                    if ($token->getType() === TokenType::TEXT) {
                        $body[] = ['type' => 'text', 'content' => $token->getValue()];
                    } elseif ($token->getType() === TokenType::VARIABLE) {
                        $body[] = $this->parseVariableExpression($token->getValue());
                    }
                }
            }

            $i++;
        }

        if ($depth > 0) {
            throw new \RuntimeException("Unterminated {$blockType} block");
        }

        return [
            'body' => $body,
            'else' => $else,
            'endPosition' => $i
        ];
    }

    private function parseCondition(string $condition): array
    {
        // Simple condition parsing for now: "player.injured" or "player.name == 'John'"
        if (str_contains($condition, '==')) {
            [$left, $right] = explode('==', $condition, 2);
            return [
                'type' => 'comparison',
                'operator' => '==',
                'left' => $this->parseVariableExpression(trim($left)),
                'right' => trim($right, '\'" ')
            ];
        }

        // Simple variable check
        return [
            'type' => 'variable',
            'expression' => $this->parseVariableExpression($condition)
        ];
    }
}